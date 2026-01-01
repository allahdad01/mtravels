<?php
/**
 * Features Helper Functions
 * Handles feature display and configuration
 */

// Define default features as constant
define('DEFAULT_FEATURES', [
    ['icon' => '✈️', 'title' => 'Ticket Management', 'description' => 'Manage ticket bookings, reservations, refunds, and date changes with automated workflows and airline integration.'],
    ['icon' => '⚖️', 'title' => 'Baggage & Ticket Weights', 'description' => 'Track baggage allowances, weight limits, and related pricing with profit monitoring.'],
    ['icon' => '🏨', 'title' => 'Hotel Management', 'description' => 'Complete hotel booking and refund system with dynamic pricing, room management, and global integrations.'],
    ['icon' => '🛂', 'title' => 'Visa Management', 'description' => 'Comprehensive visa applications, refunds, and multi-currency transactions with compliance tracking.'],
    ['icon' => '🕋', 'title' => 'Umrah Services', 'description' => 'Specialized Umrah pilgrimage bookings, refunds, and package management with compliance handling.'],
    ['icon' => '💰', 'title' => 'Financial Management', 'description' => 'Multi-currency accounting, expense tracking, salary processing, JV payments, and financial reporting with export support.'],
    ['icon' => '📑', 'title' => 'Document & Maktob Management', 'description' => 'Manage official letters, agreements, and administrative paperwork with version tracking.'],
    ['icon' => '👥', 'title' => 'Customer & Supplier Management', 'description' => 'Advanced CRM with booking history, preferences, and supplier coordination for smooth operations.'],
    ['icon' => '💬', 'title' => 'Inter-Tenant Communication', 'description' => 'Real-time messaging and collaboration tools for tenant coordination and customer support.'],
    ['icon' => '🏢', 'title' => 'Assets & Expense Management', 'description' => 'Track company assets, calculate depreciation, schedule maintenance, and manage categorized expenses.'],
    ['icon' => '📊', 'title' => 'Analytics & Reporting', 'description' => 'Interactive dashboards, KPIs, compliance reports, activity logs, and strategic insights.'],
    ['icon' => '🧾', 'title' => 'Invoice & Payment Processing', 'description' => 'Automated invoice generation, additional payments, multi-currency receipts, and digital delivery.'],
    ['icon' => '👤', 'title' => 'User & Role Management', 'description' => 'Role-based access control with permissions, security, and activity logging.'],
    ['icon' => '👥', 'title' => 'HR Management', 'description' => 'Complete human resources management including employee records, payroll, attendance, performance reviews, and organizational structure.']
]);

/**
 * Get features list from settings or use default
 */
function getFeaturesList($platform_settings) {
    $features = json_decode(getSetting($platform_settings, 'features_list', '[]'), true);
    return !empty($features) ? $features : DEFAULT_FEATURES;
}

/**
 * Calculate animation delay in seconds
 */
function getAnimationDelay($index, $interval = 0.1) {
    return $index * $interval;
}

/**
 * Render a single feature card with animation
 */
function renderFeatureCard($feature, $index) {
    $icon = htmlspecialchars($feature['icon'] ?? '🚀');
    $title = htmlspecialchars($feature['title'] ?? 'Feature Title');
    $description = htmlspecialchars($feature['description'] ?? 'Feature description');
    $delay = getAnimationDelay($index);
    
    return sprintf(
        '<div class="feature-card" style="animation-delay: %fs;">
            <div class="feature-card-inner">
                <div class="feature-icon-wrapper">
                    <div class="feature-icon">%s</div>
                </div>
                <div class="feature-content">
                    <h3>%s</h3>
                    <p>%s</p>
                    <a href="book-demo.php" class="feature-link">Try it now <span>→</span></a>
                </div>
            </div>
        </div>',
        $delay,
        $icon,
        $title,
        $description
    );
}

/**
 * Render all feature cards
 */
function renderAllFeatureCards($features) {
    $html = '';
    foreach ($features as $index => $feature) {
        $html .= renderFeatureCard($feature, $index);
    }
    return $html;
}
