<?php
/**
 * Features Helper Functions
 * Handles feature display and configuration
 * All icons use SVG (Lucide-style) - no emojis per design system rules
 */

// SVG icon map - each feature has a unique SVG icon
define('FEATURE_SVG_ICONS', [
    'ticket' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>',
    'umrah' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>',
    'visa' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M12 9v3"/><path d="M16 9v3"/><circle cx="8" cy="10" r="1.5"/><path d="M6 15h2.5a2 2 0 0 1 1.5.5L12 17l2-1.5a2 2 0 0 1 1.5-.5H18"/></svg>',
    'hotel' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M9 17h1"/><path d="M15 13h1"/><path d="M15 17h1"/></svg>',
    'finance' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    'automation' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/></svg>',
    'dashboard' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    'multitenant' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
    'roles' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    'maktob' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
    'hr' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'client' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'security' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/><circle cx="12" cy="16" r="1"/></svg>',
    'communication' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    'onboarding' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
]);

// Define default features with SVG icon keys instead of emojis
define('DEFAULT_FEATURES', [
    ['icon_key' => 'ticket', 'title' => 'Ticketing & Reservations', 'description' => 'Complete ticket booking system with reservations, refunds, date changes, weight management, and automated profit calculation.'],
    ['icon_key' => 'umrah', 'title' => 'Umrah & Family Management', 'description' => 'Family-based Umrah bookings, member management, individual payments, refunds, agreements, and ID card generation.'],
    ['icon_key' => 'visa', 'title' => 'Visa Management', 'description' => 'Visa applications, transactions, refunds, cancellations, and automated client notifications with status tracking.'],
    ['icon_key' => 'hotel', 'title' => 'Hotel Management', 'description' => 'Hotel bookings, refunds, client & supplier account linkage with automated financial impact tracking.'],
    ['icon_key' => 'finance', 'title' => 'Finance & Accounting', 'description' => 'Multi-currency support, real-time P&L, main accounts, client/supplier tracking, JV payments, and comprehensive financial statements.'],
    ['icon_key' => 'automation', 'title' => 'Automation & Intelligence', 'description' => 'Automated profit calculation, real-time analytics, interactive charts, email/WhatsApp automation, and OCR auto-fill features.'],
    ['icon_key' => 'dashboard', 'title' => 'Dashboards & Reporting', 'description' => 'Admin dashboard with multi-currency charts, profit breakdowns, outstanding dues, and exportable reports in Excel/PDF.'],
    ['icon_key' => 'multitenant', 'title' => 'Multi-Tenant & Multi-Branch', 'description' => 'Full SaaS architecture with multi-branch support, separate data per tenant, and branch-level operations management.'],
    ['icon_key' => 'roles', 'title' => 'Roles & Access Control', 'description' => 'Role-based access with Super Admin, Admin, Finance, Sales, and Umrah roles, plus branch-based user visibility.'],
    ['icon_key' => 'maktob', 'title' => 'Maktob Management', 'description' => 'Official letter management with multi-language support, PDF generation, numbering system, and audit logging.'],
    ['icon_key' => 'hr', 'title' => 'HR & Attendance', 'description' => 'Employee attendance tracking, integration with salary module, and performance-based reporting per branch.'],
    ['icon_key' => 'client', 'title' => 'Client Portal', 'description' => 'Client login access to view tickets, visas, Umrah records, balance tracking, and transparent transaction history.'],
    ['icon_key' => 'security', 'title' => 'Security & Compliance', 'description' => 'Authentication enforcement, role-based access, audit logs, tenant isolation, and secure document handling.'],
    ['icon_key' => 'communication', 'title' => 'Communication & Collaboration', 'description' => 'Inter-tenant chat, business collaboration, shared agreements, and ticket/visa selling between tenants.'],
    ['icon_key' => 'onboarding', 'title' => 'Onboarding, Support & UX', 'description' => 'Video tutorials, in-app guides, support ticket system, demo requests, and comprehensive landing pages.']
]);

/**
 * Get features list from settings or use default
 * Handles both old emoji format and new icon_key format
 */
function getFeaturesList($platform_settings) {
    $features = json_decode(getSetting($platform_settings, 'features_list', '[]'), true);
    if (!empty($features)) {
        // Convert old emoji format to new icon_key format if needed
        foreach ($features as &$feature) {
            if (isset($feature['icon']) && !isset($feature['icon_key'])) {
                $feature['icon_key'] = mapEmojiToIconKey($feature['icon']);
            }
        }
        return $features;
    }
    return DEFAULT_FEATURES;
}

/**
 * Map old emoji icons to new icon_key names
 */
function mapEmojiToIconKey($emoji) {
    $emojiMap = [
        '🧳' => 'ticket',
        '🕋' => 'umrah',
        '🛂' => 'visa',
        '🏨' => 'hotel',
        '💰' => 'finance',
        '🤖' => 'automation',
        '📊' => 'dashboard',
        '🏢' => 'multitenant',
        '👥' => 'roles',
        '🧾' => 'maktob',
        '🕒' => 'hr',
        '👤' => 'client',
        '🔐' => 'security',
        '💬' => 'communication',
        '🎓' => 'onboarding',
    ];
    return $emojiMap[$emoji] ?? 'dashboard'; // default fallback
}

/**
 * Get SVG icon HTML for a given icon key
 */
function getFeatureSvgIcon($iconKey) {
    $icons = FEATURE_SVG_ICONS;
    return $icons[$iconKey] ?? $icons['dashboard'];
}

/**
 * Calculate animation delay in seconds
 */
function getAnimationDelay($index, $interval = 0.1) {
    return $index * $interval;
}

/**
 * Render a single feature card with SVG icon and scroll reveal
 */
function renderFeatureCard($feature, $index) {
    $iconKey = $feature['icon_key'] ?? 'dashboard';
    $svgIcon = getFeatureSvgIcon($iconKey);
    $title = htmlspecialchars($feature['title'] ?? 'Feature Title');
    $description = htmlspecialchars($feature['description'] ?? 'Feature description');
    $delay = getAnimationDelay($index);
    
    return sprintf(
        '<div class="feature-card reveal reveal-up" data-delay="%d">
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
        intval($delay * 1000),
        $svgIcon,
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

/**
 * Get feature screenshots from uploads/features_images/{n+1}/
 * Returns array of full URL paths
 */
function getFeatureScreenshots($index) {
    $folderNum = str_pad($index + 1, 2, '0', STR_PAD_LEFT);
    $folderPath = __DIR__ . '/../uploads/features_images/' . $folderNum;
    $baseUrl = 'uploads/features_images/' . $folderNum;
    $images = [];
    if (is_dir($folderPath)) {
        $files = scandir($folderPath);
        $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                $images[] = $baseUrl . '/' . $file;
            }
        }
        sort($images);
    }
    return $images;
}

/**
 * Render the split-screen feature showcase
 * Left side: feature screenshots (carousel for multi-image), Right side: feature details
 * Scroll-driven navigation between features
 */
function renderFeatureSplitSection($features) {
    $total = count($features);
    if ($total === 0) return '';

    $allImages = [];
    $textHtml = '';

    foreach ($features as $i => $f) {
        $title = htmlspecialchars($f['title'] ?? '');
        $desc = htmlspecialchars($f['description'] ?? '');
        $active = $i === 0 ? ' active' : '';

        $textHtml .= sprintf(
            '<div class="ft-item%s" data-index="%d">
                <div class="ft-item-inner">
                    <h3 class="ft-title">%s</h3>
                    <p class="ft-desc">%s</p>
                    <a href="book-demo.php" class="feature-link">Try it now <span>→</span></a>
                </div>
            </div>',
            $active, $i, $title, $desc
        );
        $allImages[] = getFeatureScreenshots($i);
    }

    $imagesJson = htmlspecialchars(json_encode($allImages, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
    $totalStr = sprintf('%02d', $total);

    return sprintf(
        '<div class="features-scroll-wrap" data-feature-images=\'%s\'>
            <div class="fv-sticky">
                <div class="fv-bg"></div>
                <div class="fv-images-wrap">
                    <div class="fv-images-view">
                        <div class="fv-images-stage"></div>
                        <button class="fv-img-prev" type="button" aria-label="Previous image"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>
                        <button class="fv-img-next" type="button" aria-label="Next image"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>
                    </div>
                    <div class="fv-images-dots"></div>
                </div>
                <div class="fv-counter"><span class="fv-curnum">01</span><span class="fv-sep">/</span><span class="fv-totalnum">%s</span></div>
            </div>
            <div class="ft-list">%s</div>
            <div class="fv-progress"><div class="fv-progress-fill"></div></div>
        </div>',
        $imagesJson, $totalStr, $textHtml
    );
}
