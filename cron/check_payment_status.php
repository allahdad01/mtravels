<?php
/**
 * Payment Status Check Cron Job
 *
 * This script should be run daily via cron job to:
 * - Check tenant payment statuses
 * - Send payment reminder notifications
 * - Update tenant access based on payment status
 */

// Include database connection
require_once '../config.php';
require_once '../includes/conn.php';
require_once '../includes/db.php';

if (!isset($pdo) || !$pdo) {
    error_log("Payment status check: Database connection failed");
    exit(1);
}

echo "Starting payment status check...\n";

try {
    // Get current date
    $today = date('Y-m-d');
    $threeDaysFromNow = date('Y-m-d', strtotime('+3 days'));
    $fiveDaysAgo = date('Y-m-d', strtotime('-5 days'));

    // Get all active tenants with subscriptions
    $stmt = $pdo->prepare("
        SELECT t.*, ts.next_billing_date, ts.status as subscription_status
        FROM tenants t
        LEFT JOIN tenant_subscriptions ts ON t.id = ts.tenant_id AND ts.status = 'active'
        WHERE t.status IN ('active', 'trial')
        AND t.deleted_at IS NULL
    ");
    $stmt->execute();
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($tenants) . " active tenants to check\n";

    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        $tenantName = $tenant['name'];
        $billingEmail = $tenant['billing_email'];
        $nextBillingDate = $tenant['next_billing_date'];
        $currentPaymentStatus = $tenant['payment_status'];

        echo "Checking tenant: $tenantName (ID: $tenantId)\n";

        $newPaymentStatus = 'current';
        $updateDueDate = false;

        if ($nextBillingDate) {
            // Calculate days until due
            $daysUntilDue = (strtotime($nextBillingDate) - strtotime($today)) / (60 * 60 * 24);

            if ($daysUntilDue <= 3 && $daysUntilDue >= 0) {
                // Within 3 days of due date
                $newPaymentStatus = 'warning';
            } elseif ($daysUntilDue < 0) {
                // Past due date
                $daysOverdue = abs($daysUntilDue);

                if ($daysOverdue >= 5) {
                    $newPaymentStatus = 'suspended';
                } else {
                    $newPaymentStatus = 'overdue';
                }
            }
        } else {
            // No billing date set, assume current
            $newPaymentStatus = 'current';
        }

        // Update tenant payment status if changed
        if ($newPaymentStatus !== $currentPaymentStatus) {
            echo "  Status change: $currentPaymentStatus -> $newPaymentStatus\n";

            // Update tenant payment status
            $updateStmt = $pdo->prepare("
                UPDATE tenants
                SET payment_status = ?, payment_due_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$newPaymentStatus, $nextBillingDate, $tenantId]);

            // If suspended, also update tenant status to suspended
            if ($newPaymentStatus === 'suspended') {
                $pdo->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")
                    ->execute([$tenantId]);
                echo "  Tenant access suspended due to non-payment\n";
            }
        }

        // Send daily notifications for warning/overdue status (3 days before to 5 days after due date)
        if (in_array($newPaymentStatus, ['warning', 'overdue'])) {
            sendDailyPaymentWarningNotification($tenant, $pdo, $daysUntilDue);
        }

        // Reset warning sent flag if payment becomes current
        if ($newPaymentStatus === 'current' && $tenant['payment_warning_sent']) {
            $pdo->prepare("UPDATE tenants SET payment_warning_sent = 0, last_warning_sent = NULL WHERE id = ?")
                ->execute([$tenantId]);
        }
    }

    echo "Payment status check completed successfully\n";

} catch (Exception $e) {
    error_log("Payment status check error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Send daily payment warning notification (3 days before to 5 days after due date)
 */
function sendDailyPaymentWarningNotification($tenant, $pdo, $daysUntilDue) {
    try {
        $lastWarningSent = $tenant['last_warning_sent'];
        $now = new DateTime();
        $shouldSendNotification = false;

        // Check if we should send a notification today
        if ($lastWarningSent === null) {
            // Never sent a warning before, send one
            $shouldSendNotification = true;
        } else {
            // Check if it's been at least 24 hours since last notification
            $lastWarningDateTime = new DateTime($lastWarningSent);
            $hoursSinceLastWarning = $now->diff($lastWarningDateTime)->h + ($now->diff($lastWarningDateTime)->days * 24);

            if ($hoursSinceLastWarning >= 24) {
                $shouldSendNotification = true;
            }
        }

        if (!$shouldSendNotification) {
            return; // Don't send notification today
        }

        // Create appropriate message based on days until due
        if ($daysUntilDue >= 0) {
            // Before or on due date
            $daysText = $daysUntilDue === 0 ? "today" : "in {$daysUntilDue} day" . ($daysUntilDue > 1 ? "s" : "");
            $urgency = $daysUntilDue <= 1 ? "URGENT: " : "";
            $message = "
{$urgency}Payment Due {$daysText} - Action Required

Dear {$tenant['name']},

This is an automated reminder that your subscription payment is due {$daysText} ({$tenant['next_billing_date']}).

Please ensure your payment is processed before the due date to avoid service interruption.

If you have already made the payment, please disregard this notice.

Best regards,
Mtravels Support Team
            ";
        } else {
            // After due date (overdue)
            $daysOverdue = abs($daysUntilDue);
            $daysText = $daysOverdue === 1 ? "1 day" : "{$daysOverdue} days";
            $message = "
OVERDUE PAYMENT - {$daysText} Past Due Date

Dear {$tenant['name']},

Your subscription payment was due on {$tenant['next_billing_date']} and is now {$daysText} overdue.

Immediate payment is required to restore full access to your account. Continued non-payment may result in account suspension.

Please contact support or make payment immediately.

Best regards,
Mtravels Support Team
            ";
        }

        // Insert notification into notifications table for tenant admins
        $stmt = $pdo->prepare("
            INSERT INTO notifications
            (tenant_id, transaction_id, transaction_type, message, recipient_role, status)
            VALUES (?, NULL, 'mtravels', ?, 'Admin', 'Unread')
        ");
        $stmt->execute([$tenant['id'], trim($message)]);

        // Update last warning sent timestamp
        $pdo->prepare("UPDATE tenants SET last_warning_sent = NOW(), payment_warning_sent = 1 WHERE id = ?")
            ->execute([$tenant['id']]);

        echo "  Daily payment warning notification sent to tenant: {$tenant['name']} ({$daysUntilDue} days until due)\n";

    } catch (Exception $e) {
        error_log("Error sending daily payment warning: " . $e->getMessage());
    }
}
?>