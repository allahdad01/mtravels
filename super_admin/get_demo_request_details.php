<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin' || !is_null($_SESSION['tenant_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

// Database connection
require_once '../includes/conn.php';

$request_id = intval($_GET['id'] ?? 0);
$basic = isset($_GET['basic']);

if (!$request_id) {
    http_response_code(400);
    exit('Invalid request ID');
}

try {
    $stmt = $conn->prepare("SELECT * FROM demo_requests WHERE id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$request) {
        http_response_code(404);
        exit('Request not found');
    }

    if ($basic) {
        // Return basic info for delete confirmation
        ?>
        <div class="alert alert-warning">
            <strong>Request Details:</strong><br>
            Name: <?= htmlspecialchars($request['name']) ?><br>
            Email: <?= htmlspecialchars($request['email']) ?><br>
            Company: <?= htmlspecialchars($request['company']) ?><br>
            Status: <?= ucfirst(htmlspecialchars($request['status'])) ?><br>
            Created: <?= date('M d, Y H:i A', strtotime($request['created_at'])) ?>
        </div>
        <?php
    } else {
        // Return full details for view modal
        ?>
        <div class="row">
            <div class="col-md-6">
                <h6 class="font-weight-bold mb-3">Contact Information</h6>
                <div class="mb-2">
                    <strong>Name:</strong> <?= htmlspecialchars($request['name']) ?>
                </div>
                <div class="mb-2">
                    <strong>Email:</strong>
                    <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="text-primary">
                        <?= htmlspecialchars($request['email']) ?>
                    </a>
                </div>
                <?php if ($request['phone']): ?>
                <div class="mb-2">
                    <strong>Phone:</strong>
                    <a href="tel:<?= htmlspecialchars($request['phone']) ?>" class="text-primary">
                        <?= htmlspecialchars($request['phone']) ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6 class="font-weight-bold mb-3">Company Information</h6>
                <div class="mb-2">
                    <strong>Company:</strong> <?= htmlspecialchars($request['company']) ?>
                </div>
                <?php if ($request['company_size']): ?>
                <div class="mb-2">
                    <strong>Company Size:</strong> <?= htmlspecialchars($request['company_size']) ?> employees
                </div>
                <?php endif; ?>
                <div class="mb-2">
                    <strong>Status:</strong>
                    <span class="badge badge-<?= getStatusBadgeClass($request['status']) ?>">
                        <?= ucfirst(htmlspecialchars($request['status'])) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if ($request['preferred_date'] || $request['preferred_time']): ?>
        <div class="row mt-3">
            <div class="col-md-12">
                <h6 class="font-weight-bold mb-3">Preferred Schedule</h6>
                <div class="row">
                    <?php if ($request['preferred_date']): ?>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Preferred Date:</strong>
                            <span class="badge badge-info">
                                <i class="feather icon-calendar mr-1"></i>
                                <?= date('l, F d, Y', strtotime($request['preferred_date'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($request['preferred_time']): ?>
                    <div class="col-md-6">
                        <div class="mb-2">
                            <strong>Preferred Time:</strong>
                            <span class="badge badge-info">
                                <i class="feather icon-clock mr-1"></i>
                                <?= date('H:i A', strtotime($request['preferred_time'])) ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($request['message']): ?>
        <div class="row mt-3">
            <div class="col-md-12">
                <h6 class="font-weight-bold mb-3">Message</h6>
                <div class="bg-light p-3 rounded">
                    <?= nl2br(htmlspecialchars($request['message'])) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row mt-3">
            <div class="col-md-12">
                <h6 class="font-weight-bold mb-3">Timeline</h6>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Request Submitted</h6>
                            <small class="text-muted">
                                <?= date('F d, Y \a\t H:i A', strtotime($request['created_at'])) ?>
                            </small>
                        </div>
                    </div>
                    <?php if ($request['updated_at'] !== $request['created_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-warning"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Last Updated</h6>
                            <small class="text-muted">
                                <?= date('F d, Y \a\t H:i A', strtotime($request['updated_at'])) ?>
                            </small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 3px solid #fff;
        }
        .timeline-content h6 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        </style>
        <?php
    }
} catch (Exception $e) {
    error_log("Error fetching demo request details: " . $e->getMessage());
    http_response_code(500);
    exit('Database error');
}

function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'warning',
        'contacted' => 'info',
        'scheduled' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $classes[$status] ?? 'secondary';
}
?>