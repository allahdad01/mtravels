<?php
/**
 * Finance Tracker Widget Component
 * 
 * This is a reusable widget component that can be embedded in any dashboard
 * 
 * Usage:
 * include '../modals/finance_tracker_widget.php';
 */

if (!isset($_SESSION)) {
    session_start();
}

$tenant_id = $_SESSION['tenant_id'] ?? null;
$branch_id = $_SESSION['branch_id'] ?? null;
$csrf_token = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>

<style>
    .finance-widget-container {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }

    .finance-widget-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .finance-widget-header h3 {
        margin: 0;
        color: #1e3a8a;
        font-size: 1.3rem;
    }

    .finance-widget-refresh {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0.5rem;
        transition: transform 0.3s;
    }

    .finance-widget-refresh:hover {
        transform: rotate(180deg);
    }

    .finance-widget-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    @media (max-width: 576px) {
        .finance-widget-grid {
            grid-template-columns: 1fr;
        }
    }

    .finance-widget-stat {
        padding: 1rem;
        background: #f8fafc;
        border-radius: 6px;
        border-left: 4px solid #1e3a8a;
    }

    .finance-widget-stat.income {
        border-left-color: #16a34a;
    }

    .finance-widget-stat.expense {
        border-left-color: #dc2626;
    }

    .finance-widget-stat-label {
        font-size: 0.85rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .finance-widget-stat-value {
        font-size: 1.5rem;
        font-weight: bold;
        color: #1e3a8a;
    }

    .finance-widget-stat.income .finance-widget-stat-value {
        color: #16a34a;
    }

    .finance-widget-stat.expense .finance-widget-stat-value {
        color: #dc2626;
    }

    .finance-widget-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .finance-widget-btn {
        flex: 1;
        min-width: 120px;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
        font-size: 0.9rem;
    }

    .finance-widget-btn-primary {
        background: #1e3a8a;
        color: white;
    }

    .finance-widget-btn-primary:hover {
        background: #1e3a8a;
        opacity: 0.9;
    }

    .finance-widget-btn-secondary {
        background: #e2e8f0;
        color: #1e3a8a;
    }

    .finance-widget-btn-secondary:hover {
        background: #cbd5e1;
    }

    .finance-widget-loading {
        text-align: center;
        padding: 1rem;
        color: #64748b;
    }

    .finance-widget-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #e2e8f0;
        border-top-color: #1e3a8a;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }
</style>

<div class="finance-widget-container">
    <div class="finance-widget-header">
        <h3>💰 Finance Tracker</h3>
        <button class="finance-widget-refresh" onclick="financeWidget_refresh()" title="Refresh data">🔄</button>
    </div>

    <div id="financeWidget_loading" class="finance-widget-loading" style="display: none;">
        <div class="finance-widget-spinner"></div>
        <p>Loading...</p>
    </div>

    <div id="financeWidget_content" style="display: none;">
        <div class="finance-widget-grid">
            <div class="finance-widget-stat">
                <div class="finance-widget-stat-label">💵 USD Balance</div>
                <div class="finance-widget-stat-value" id="financeWidget_usdBalance">$0.00</div>
            </div>
            <div class="finance-widget-stat">
                <div class="finance-widget-stat-label">💴 AFS Balance</div>
                <div class="finance-widget-stat-value" id="financeWidget_afsBalance">₨0.00</div>
            </div>
            <div class="finance-widget-stat income">
                <div class="finance-widget-stat-label">📈 Today's Income (USD)</div>
                <div class="finance-widget-stat-value" id="financeWidget_todayIncomeUsd">$0.00</div>
            </div>
            <div class="finance-widget-stat expense">
                <div class="finance-widget-stat-label">📉 Today's Expense (USD)</div>
                <div class="finance-widget-stat-value" id="financeWidget_todayExpenseUsd">$0.00</div>
            </div>
        </div>

        <div class="finance-widget-actions">
            <button class="finance-widget-btn finance-widget-btn-primary" onclick="financeWidget_openTracker()">📊 Open Full Tracker</button>
            <button class="finance-widget-btn finance-widget-btn-secondary" onclick="financeWidget_refresh()">🔄 Refresh</button>
        </div>
    </div>
</div>

<script>
    const FINANCE_WIDGET_BASE_URL = '<?php echo isset($_SESSION['base_url']) ? $_SESSION['base_url'] : ''; ?>/api/finance/finance_tracker_actions.php';

    function financeWidget_refresh() {
        const loading = document.getElementById('financeWidget_loading');
        const content = document.getElementById('financeWidget_content');

        loading.style.display = 'block';
        content.style.display = 'none';

        fetch(FINANCE_WIDGET_BASE_URL + '?action=get_balances')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('financeWidget_usdBalance').textContent = 
                        '$' + parseFloat(data.usd_balance).toFixed(2);
                    document.getElementById('financeWidget_afsBalance').textContent = 
                        '₨' + parseFloat(data.afs_balance).toFixed(2);
                    document.getElementById('financeWidget_todayIncomeUsd').textContent = 
                        '$' + parseFloat(data.today_income_usd).toFixed(2);
                    document.getElementById('financeWidget_todayExpenseUsd').textContent = 
                        '$' + parseFloat(data.today_expense_usd).toFixed(2);

                    loading.style.display = 'none';
                    content.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error loading finance widget:', error);
                loading.style.display = 'none';
                loading.innerHTML = '<p style="color: #dc2626;">Error loading data</p>';
            });
    }

    function financeWidget_openTracker() {
        window.location.href = '<?php echo isset($_SESSION['base_url']) ? $_SESSION['base_url'] : ''; ?>/admin/finance_tracker.php';
    }

    // Auto-load on page ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', financeWidget_refresh);
    } else {
        financeWidget_refresh();
    }

    // Auto-refresh every 2 minutes
    setInterval(financeWidget_refresh, 120000);
</script>
