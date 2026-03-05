<?php
/**
 * Staff Dashboard View
 * Simplified dashboard showing only attendance and payments
 */
?>

<link rel="stylesheet" href="../css/dashboard/dashboard.css">
<link href="../css/dashboard/dashboard-styles.css" rel="stylesheet">
<style>
    .main {
        padding: 30px;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
    }

    .page-header {
        margin-bottom: 30px;
        color: #1a202c;
    }

    .greeting {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .greeting-name {
        color: #4099ff;
        font-weight: 800;
    }

    .subtext {
        font-size: 14px;
        color: #718096;
        margin: 0;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .stat-label {
        font-size: 12px;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 10px;
        font-weight: 600;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #4099ff;
        margin-bottom: 5px;
    }

    .stat-sub {
        font-size: 12px;
        color: #a0aec0;
    }

    .bottom-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
    }

    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        color: white;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-title {
        font-size: 16px;
        font-weight: 700;
        margin: 0;
    }

    .view-link {
        color: white;
        text-decoration: none;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .view-link:hover {
        opacity: 0.8;
    }

    .att-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .att-row:last-child {
        border-bottom: none;
    }

    .label {
        font-size: 14px;
        color: #718096;
        font-weight: 500;
    }

    .val {
        font-size: 14px;
        font-weight: 700;
        color: #2d3748;
    }

    .progress-wrap {
        padding: 20px;
        border-top: 1px solid #e2e8f0;
    }

    .progress-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 12px;
        color: #718096;
    }

    .progress-meta strong {
        color: #2d3748;
        font-weight: 700;
    }

    .progress-track {
        background: #e2e8f0;
        border-radius: 8px;
        height: 8px;
        overflow: hidden;
    }

    .progress-fill {
        background: linear-gradient(90deg, #4099ff 0%, #2ed8b6 100%);
        height: 100%;
        border-radius: 8px;
        transition: width 0.3s ease;
    }

    .pay-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .pay-row:last-child {
        border-bottom: none;
    }

    .pay-desc {
        font-size: 14px;
        font-weight: 600;
        color: #2d3748;
    }

    .pay-date {
        font-size: 12px;
        color: #718096;
        margin-top: 3px;
    }

    .pay-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
    }

    .pay-amount {
        font-size: 14px;
        font-weight: 700;
        color: #2d3748;
    }

    .badge {
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
    }

    .badge-paid {
        background: #c6f6d5;
        color: #22543d;
    }

    .badge-pending {
        background: #fed7d7;
        color: #742a2a;
    }

    .badge-default {
        background: #e2e8f0;
        color: #2d3748;
    }

    .empty-state {
        padding: 40px 20px;
        text-align: center;
        color: #a0aec0;
        font-size: 14px;
    }

    :root {
        --green: #48bb78;
        --red: #f56565;
        --yellow: #ecc94b;
    }
</style>

<!-- [ Main Content ] start -->
<div class="pcoded-main-container">
    <div class="pcoded-wrapper">
        <div class="pcoded-content">
            <div class="pcoded-inner-content">
                <div class="main-body">
                    <div class="page-wrapper">
                        <!-- [ Main Content ] start -->
                        
                            <div class="page-header">
                                <h1 class="greeting">Welcome back, <span class="greeting-name"><?php echo htmlspecialchars($first_name); ?></span> 👋</h1>
                                <p class="subtext">Here's your summary for <?php echo date('F Y'); ?></p>
                            </div>

                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-label">Total Days</div>
                                    <div class="stat-value"><?php echo $total; ?></div>
                                    <div class="stat-sub">Working days this month</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Present</div>
                                    <div class="stat-value"><?php echo $present; ?></div>
                                    <div class="stat-sub"><?php echo $rate; ?>% attendance rate</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">Absent</div>
                                    <div class="stat-value"><?php echo $absent; ?></div>
                                    <div class="stat-sub">Unexcused absences</div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-label">On Leave</div>
                                    <div class="stat-value"><?php echo $leave; ?></div>
                                    <div class="stat-sub">Approved leave days</div>
                                </div>
                            </div>

                            <div class="bottom-grid">

                                <div class="card">
                                    <div class="card-header">
                                        <span class="card-title">Attendance Summary</span>
                                        <a href="attendance.php" class="view-link">View Full Details →</a>
                                    </div>
                                    <div class="att-row"><span class="label">Total Days</span><span class="val"><?php echo $total; ?></span></div>
                                    <div class="att-row"><span class="label">Present</span><span class="val" style="color:var(--green)"><?php echo $present; ?></span></div>
                                    <div class="att-row"><span class="label">Absent</span><span class="val" style="color:var(--red)"><?php echo $absent; ?></span></div>
                                    <div class="att-row"><span class="label">Leave</span><span class="val" style="color:var(--yellow)"><?php echo $leave; ?></span></div>
                                    <div class="progress-wrap">
                                        <div class="progress-meta"><span>Attendance rate</span><strong><?php echo $rate; ?>%</strong></div>
                                        <div class="progress-track"><div class="progress-fill" style="width:<?php echo $rate; ?>%"></div></div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <span class="card-title">Recent Payments</span>
                                        <a href="salary_payments.php" class="view-link">View All →</a>
                                    </div>
                                    <?php if (count($recent_payments) > 0): ?>
                                        <?php foreach ($recent_payments as $payment):
                                            $status    = strtolower($payment['status'] ?? 'salary');
                                            // Determine badge class based on payment type
                                            if (stripos($status, 'salary') !== false) {
                                                $badge_cls = 'badge-paid';
                                            } elseif (stripos($status, 'advance') !== false) {
                                                $badge_cls = 'badge-pending';
                                            } else {
                                                $badge_cls = 'badge-default';
                                            }
                                        ?>
                                        <div class="pay-row">
                                            <div>
                                                <div class="pay-desc"><?php echo htmlspecialchars($payment['description'] ?? 'Payment'); ?></div>
                                                <div class="pay-date"><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></div>
                                            </div>
                                            <div class="pay-right">
                                                <span class="pay-amount">$<?php echo number_format($payment['amount'], 2); ?></span>
                                                <span class="badge <?php echo $badge_cls; ?>"><?php echo ucfirst(str_replace('_', ' ', $status)); ?></span>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-state">No payments yet.</div>
                                    <?php endif; ?>
                                </div>

                            </div>

                        <!-- [ Main Content ] end -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- [ Main Content ] end -->
    <!-- Required Js -->
    
    <script src="../assets/js/vendor-all.min.js"></script>
	<script src="../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../assets/js/pcoded.min.js"></script>
</body>
</html>
