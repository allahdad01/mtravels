<?php
/**
 * Features Helper Functions
 * Handles feature display and configuration
 */

// Define default features as constant
define('DEFAULT_FEATURES', [
    ['icon' => '🧳', 'title' => 'Ticketing & Reservations', 'description' => 'Complete ticket booking system with reservations, refunds, date changes, weight management, and automated profit calculation.'],
    ['icon' => '🕋', 'title' => 'Umrah & Family Management', 'description' => 'Family-based Umrah bookings, member management, individual payments, refunds, agreements, and ID card generation.'],
    ['icon' => '🛂', 'title' => 'Visa Management', 'description' => 'Visa applications, transactions, refunds, cancellations, and automated client notifications with status tracking.'],
    ['icon' => '🏨', 'title' => 'Hotel Management', 'description' => 'Hotel bookings, refunds, client & supplier account linkage with automated financial impact tracking.'],
    ['icon' => '💰', 'title' => 'Finance & Accounting', 'description' => 'Multi-currency support, real-time P&L, main accounts, client/supplier tracking, JV payments, and comprehensive financial statements.'],
    ['icon' => '🤖', 'title' => 'Automation & Intelligence', 'description' => 'Automated profit calculation, real-time analytics, interactive charts, email/WhatsApp automation, and OCR auto-fill features.'],
    ['icon' => '📊', 'title' => 'Dashboards & Reporting', 'description' => 'Admin dashboard with multi-currency charts, profit breakdowns, outstanding dues, and exportable reports in Excel/PDF.'],
    ['icon' => '🏢', 'title' => 'Multi-Tenant & Multi-Branch', 'description' => 'Full SaaS architecture with multi-branch support, separate data per tenant, and branch-level operations management.'],
    ['icon' => '👥', 'title' => 'Roles & Access Control', 'description' => 'Role-based access with Super Admin, Admin, Finance, Sales, and Umrah roles, plus branch-based user visibility.'],
    ['icon' => '🧾', 'title' => 'Maktob Management', 'description' => 'Official letter management with multi-language support, PDF generation, numbering system, and audit logging.'],
    ['icon' => '🕒', 'title' => 'HR & Attendance', 'description' => 'Employee attendance tracking, integration with salary module, and performance-based reporting per branch.'],
    ['icon' => '👤', 'title' => 'Client Portal', 'description' => 'Client login access to view tickets, visas, Umrah records, balance tracking, and transparent transaction history.'],
    ['icon' => '🔐', 'title' => 'Security & Compliance', 'description' => 'Authentication enforcement, role-based access, audit logs, tenant isolation, and secure document handling.'],
    ['icon' => '💬', 'title' => 'Communication & Collaboration', 'description' => 'Inter-tenant chat, business collaboration, shared agreements, and ticket/visa selling between tenants.'],
    ['icon' => '🎓', 'title' => 'Onboarding, Support & UX', 'description' => 'Video tutorials, in-app guides, support ticket system, demo requests, and comprehensive landing pages.']
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
