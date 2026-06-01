<?php
session_start();

require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/helpers.php';
require_once 'includes/theme-helper.php';

$default_tenant_id = 1;

function getPlatformSettings($pdo) {
    $cache_key = 'platform_settings_' . md5('platform_settings');
    
    if (function_exists('getCachedData') && $cached = getCachedData($cache_key)) {
        return $cached;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        
        if (function_exists('setCachedData')) {
            setCachedData($cache_key, $settings);
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

$platform_settings = getPlatformSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Transparent, flexible pricing plans for travel agencies of all sizes. From basic ticket management to enterprise multi-branch operations.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/pricing.css">
    <?php renderThemeStyles(); ?>
</head>
<body>
    <div class="animated-bg"></div>
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <?php 
    $nav_links = [
        ['href' => 'index.php', 'label' => 'Home'],
        ['href' => 'index.php#features', 'label' => 'Features'],
        ['href' => 'pricing.php', 'label' => 'Pricing'],
        ['href' => 'about.php', 'label' => 'About'],
        ['href' => 'index.php#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php'; 
    ?>

    <div class="pricing-wrapper">

    <!-- Hero -->
    <section class="pricing-hero">
        <div class="pricing-hero-content">
            <div class="pricing-hero-badge fade-up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Simple, transparent pricing
            </div>
            <h1 class="fade-up fade-up-d1">Plans that <span>scale</span> with your agency</h1>
            <p class="fade-up fade-up-d2">Whether you manage tickets only or operate a full multi-branch Umrah & travel business, our platform grows with you.</p>
        </div>
    </section>

    <!-- Billing Toggle -->
    <section class="pricing-controls fade-up">
        <div class="billing-toggle">
            <div class="toggle-wrap">
                <span class="toggle-text" id="monthlyLabel">Monthly</span>
                <div class="toggle-track" id="toggleTrack" role="button" tabindex="0" aria-label="Toggle billing period">
                    <div class="toggle-thumb"></div>
                </div>
                <span class="toggle-text" id="yearlyLabel">Annual</span>
            </div>
            <div class="savings-badge">Save ~20% with annual</div>
        </div>
    </section>

    <!-- Pricing Cards -->
    <section class="pricing-section">
        <div class="pricing-container">
            <div class="pricing-grid">

                <!-- Basic -->
                <div class="pricing-card fade-up">
                    <div class="plan-header">
                        <div class="plan-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        </div>
                        <span class="plan-name">Basic</span>
                    </div>
                    <div class="plan-subtitle">For small ticket-focused agencies</div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">1,000</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="annual-price" style="display:none">AFN 9,600/year — save AFN 2,400</div>
                    <p class="plan-desc">Everything you need to manage airline tickets with strong financial control.</p>
                    <a href="login.php?plan=basic" class="btn btn-primary">Start Free Trial</a>
                    <a href="book-demo.php" class="btn btn-outline">Get Started</a>
                    <div class="card-features">
                        <div class="card-features-title">Key features</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Ticket bookings, reservations & refunds</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Multi-currency & financial statements</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Expense & cash flow tracking</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Daily operations & transaction history</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Up to 10 users + support ticket system</div>
                    </div>
                    <div class="plan-tag">
                        <span class="plan-tag-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></span>
                        Ideal for agencies starting digital operations
                    </div>
                </div>

                <!-- Umrah -->
                <div class="pricing-card fade-up fade-up-d1">
                    <div class="plan-header">
                        <div class="plan-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </div>
                        <span class="plan-name">Umrah</span>
                    </div>
                    <div class="plan-subtitle">For Umrah-only agencies</div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">2,000</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="annual-price" style="display:none">AFN 19,200/year — save AFN 4,800</div>
                    <p class="plan-desc">Built for Umrah-focused businesses managing families, payments, and compliance.</p>
                    <a href="login.php?plan=umrah" class="btn btn-primary">Start Free Trial</a>
                    <a href="book-demo.php" class="btn btn-outline">Get Started</a>
                    <div class="card-features">
                        <div class="card-features-title">Key features</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Family & member management</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> ID card & agreement generation</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Payment processing & multi-currency</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Cancellation & refund processing</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Up to 10 users + support ticket system</div>
                    </div>
                    <div class="plan-tag">
                        <span class="plan-tag-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></span>
                        Perfect for Umrah agencies needing structure
                    </div>
                </div>

                <!-- Pro -->
                <div class="pricing-card fade-up fade-up-d2">
                    <div class="plan-header">
                        <div class="plan-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                        <span class="plan-name">Pro</span>
                    </div>
                    <div class="plan-subtitle">For growing multi-service agencies</div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">2,500</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="annual-price" style="display:none">AFN 24,000/year — save AFN 6,000</div>
                    <p class="plan-desc">Complete solution for agencies selling tickets, hotels, and visas with deeper workflows.</p>
                    <a href="login.php?plan=pro" class="btn btn-primary">Start Free Trial</a>
                    <a href="book-demo.php" class="btn btn-outline">Get Started</a>
                    <div class="card-features">
                        <div class="card-features-title">Key features</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Everything in Basic</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Hotel bookings, refunds & management</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Visa applications, refunds & transactions</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Additional payments & linked records</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Up to 15 users + priority support</div>
                    </div>
                    <div class="plan-tag">
                        <span class="plan-tag-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></span>
                        Best for agencies expanding beyond tickets
                    </div>
                </div>

                <!-- Enterprise (Featured) -->
                <div class="pricing-card featured-card fade-up fade-up-d3">
                    <div class="popular-badge">Most Popular</div>
                    <div class="plan-header">
                        <div class="plan-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15l-3-3 3-3 3 3-3 3z"/><path d="M12 19l-7-7 7-7 7 7-7 7z"/></svg>
                        </div>
                        <span class="plan-name">Enterprise</span>
                    </div>
                    <div class="plan-subtitle">For large multi-branch operations</div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">4,500</span>
                        <span class="period">/month</span>
                    </div>
                    <div class="annual-price" style="display:none">AFN 43,200/year — save AFN 10,800</div>
                    <p class="plan-desc">Full control, automation, and reporting across branches with enterprise features.</p>
                    <a href="login.php?plan=enterprise" class="btn btn-primary">Start Free Trial</a>
                    <a href="book-demo.php" class="btn btn-outline">Get Started</a>
                    <div class="card-features">
                        <div class="card-features-title">Key features</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Everything in Pro</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Advanced Umrah management + ID cards</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Debtors, creditors & Sarafi management</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Salary, JV payments & asset management</div>
                        <div class="card-feature"><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Up to 30 users + super admin dashboard</div>
                    </div>
                    <div class="plan-tag">
                        <span class="plan-tag-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></span>
                        Designed for high-volume, multi-branch agencies
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Included in All Plans -->
    <section class="included-section fade-up">
        <div class="included-container">
            <div class="section-label">Included everywhere</div>
            <h2>Every plan includes</h2>
            <p class="section-sub">Core platform features available on all tiers — no hidden add-ons.</p>
            <div class="included-grid">

                <div class="included-item fade-up">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <h4>Secure Cloud</h4>
                    <p>AES-256 encrypted cloud infrastructure</p>
                </div>
                <div class="included-item fade-up fade-up-d1">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </div>
                    <h4>Email Automation</h4>
                    <p>Automated notifications & workflows</p>
                </div>
                <div class="included-item fade-up fade-up-d2">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h4>WhatsApp Automation</h4>
                    <p>Tenant-managed messaging system</p>
                </div>
                <div class="included-item fade-up fade-up-d3">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a4 4 0 0 0-4 4v2a4 4 0 0 0 8 0V6a4 4 0 0 0-4-4z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" y1="19" x2="12" y2="22"/></svg>
                    </div>
                    <h4>OCR Technology</h4>
                    <p>Auto-fill tickets & passport data</p>
                </div>
                <div class="included-item fade-up">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    </div>
                    <h4>Dashboards</h4>
                    <p>Real-time insights & analytics</p>
                </div>
                <div class="included-item fade-up fade-up-d1">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h4>Audit Logs</h4>
                    <p>Complete change history & compliance</p>
                </div>
                <div class="included-item fade-up fade-up-d2">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h4>Role-Based Access</h4>
                    <p>Granular permission management</p>
                </div>
                <div class="included-item fade-up fade-up-d3">
                    <div class="included-icon-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </div>
                    <h4>Multi-Currency</h4>
                    <p>Support for AFN, USD, EUR & more</p>
                </div>

            </div>
        </div>
    </section>

    <!-- Comparison Table -->
    <section class="comparison-section fade-up">
        <div class="comparison-container">
            <h2>Compare plans in detail</h2>
            <p class="section-sub">See exactly what's included in each plan.</p>
            <div class="compare-table-wrap">
                <table class="compare-table">
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Basic</th>
                            <th>Umrah</th>
                            <th>Pro</th>
                            <th>Enterprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td class="feature-name">Max Users</td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> 10</td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> 10</td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> 15</td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> 30</td></tr>
                        <tr><td class="feature-name">Ticket Management</td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Umrah Management</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Advanced</td></tr>
                        <tr><td class="feature-name">Hotel Services</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Visa Services</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Financial Statements</td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span> Advanced</td></tr>
                        <tr><td class="feature-name">Debtors & Creditors</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Sarafi Management</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Salary & JV Payments</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Priority Support</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Super Admin Dashboard</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                        <tr><td class="feature-name">Branch Performance Reports</td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="tm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></span></td><td><span class="cm-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></span></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="faq-section fade-up">
        <div class="faq-container">
            <h2>Frequently asked questions</h2>
            <p class="section-sub">Everything you need to know about our pricing.</p>
            <div class="faq-grid">
                <div class="faq-item"><h4>Can I upgrade or downgrade anytime?</h4><p>Yes! Change your plan at any time. Upgrades are prorated, downgrades receive credit for the next billing cycle.</p></div>
                <div class="faq-item"><h4>What payment methods do you accept?</h4><p>We accept bank transfers, credit/debit cards, and local payment methods. Contact sales for invoicing.</p></div>
                <div class="faq-item"><h4>Is there a setup fee?</h4><p>No hidden fees. The price you see is what you pay. All plans include free onboarding and setup assistance.</p></div>
                <div class="faq-item"><h4>Do you offer annual discounts?</h4><p>Yes! Switch to annual billing and save 20% on any plan. Enterprise customers can negotiate custom pricing.</p></div>
                <div class="faq-item"><h4>What if I exceed my user limit?</h4><p>Add additional users anytime at a pro-rata cost. Request more users in your account settings.</p></div>
                <div class="faq-item"><h4>Is data secure and backed up?</h4><p>All data is AES-256 encrypted, backed up daily, and compliant with international security standards.</p></div>
                <div class="faq-item"><h4>Can I customize Enterprise?</h4><p>Yes! Larger organizations can customize features, workflows, and integrations. Contact our team.</p></div>
                <div class="faq-item"><h4>What happens after my free trial?</h4><p>After 14 days, start a paid subscription. We'll never charge without your consent. Cancel anytime.</p></div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="pricing-cta fade-up">
        <div class="cta-container">
            <h2>Not sure which plan is right for you?</h2>
            <p>Start with a 14-day free trial — no credit card required. Or let our team recommend the perfect plan.</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary">Start Free Trial</a>
                <a href="book-demo.php" class="btn btn-outline">Talk to Sales</a>
            </div>
        </div>
    </section>

    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script>
    // Billing toggle
    const track = document.getElementById('toggleTrack');
    const monthlyPrices = document.querySelectorAll('.monthly-price');
    const annualPrices = document.querySelectorAll('.annual-price');

    if (track) {
        track.addEventListener('click', function() {
            const isAnnual = this.classList.toggle('active');
            monthlyPrices.forEach(p => p.style.display = isAnnual ? 'none' : '');
            annualPrices.forEach(p => p.style.display = isAnnual ? 'block' : 'none');
        });
    }

    // Intersection Observer for entrance animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: .15, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    </script>
    <?php renderThemeScript(); ?>
</body>
</html>
