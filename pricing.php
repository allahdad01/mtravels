<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/helpers.php';
require_once 'includes/theme-helper.php';

// Default tenant ID for landing page
$default_tenant_id = 1;

// Optimized function to fetch platform settings with caching
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
    <!-- Navigation -->
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

    <!-- Hero Section -->
    <section class="pricing-hero">
        <div class="container">
            <h1>Transparent Pricing for Every Travel Agency</h1>
            <p class="hero-subtitle">Whether you manage tickets only or operate a full multi-branch Umrah & travel business, our platform scales with you.</p>
            <p class="hero-description">All plans include secure cloud access, regular updates, core automation features, and dedicated support.</p>
        </div>
    </section>

    <!-- Toggle Section -->
    <section class="pricing-controls">
        <div class="container">
            <div class="billing-toggle">
                <label class="toggle-label">
                    <input type="checkbox" id="billingToggle" class="toggle-checkbox">
                    <span class="toggle-text">Save 20% with annual billing</span>
                </label>
                <div class="savings-badge">Save AFN 2,400/year</div>
            </div>
        </div>
    </section>

    <!-- Pricing Cards Section -->
    <section class="pricing-section">
        <div class="container">
            <div class="pricing-grid">
                <!-- Basic Plan -->
                <div class="pricing-card">
                    <div class="plan-badge basic-badge">🟢 Basic</div>
                    <div class="plan-header">
                        <h3>Basic Plan</h3>
                        <p class="plan-subtitle">Best for small ticket-focused agencies</p>
                    </div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">1,000</span>
                        <span class="period">/month</span>
                        <span class="annual-price" style="display: none;">AFN 9,600/year</span>
                    </div>
                    <p class="plan-description">Designed for agencies that primarily sell airline tickets and want strong financial control without complexity.</p>
                    
                    <button class="btn btn-outline btn-block" onclick="window.location.href='book-demo.php'">Get Started</button>
                    <button class="btn btn-secondary btn-block" onclick="window.location.href='login.php?plan=basic'">Start Free Trial</button>

                    <div class="features-section">
                        <h4>What's Included:</h4>
                        <div class="features-group">
                            <div class="feature-group-title">✈️ Ticket Management</div>
                            <ul class="features-list">
                                <li><span class="checkmark">✓</span> Ticket Bookings</li>
                                <li><span class="checkmark">✓</span> Ticket Reservations</li>
                                <li><span class="checkmark">✓</span> Refunded Tickets</li>
                                <li><span class="checkmark">✓</span> Date Change Tickets</li>
                                <li><span class="checkmark">✓</span> Ticket Weights & Profit Tracking</li>
                            </ul>
                        </div>

                        <div class="feature-group-title">💰 Finance & Accounting</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Multi-currency Support</li>
                            <li><span class="checkmark">✓</span> Financial Statements</li>
                            <li><span class="checkmark">✓</span> Expense Management</li>
                            <li><span class="checkmark">✓</span> Cash Flow Tracking</li>
                        </ul>

                        <div class="feature-group-title">🏢 Business Operations</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Daily Operations Logging</li>
                            <li><span class="checkmark">✓</span> Transaction History</li>
                        </ul>

                        <div class="feature-group-title">💬 Communication</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Inter-Tenant Chat</li>
                            <li><span class="checkmark">✓</span> Email Notifications</li>
                        </ul>

                        <div class="feature-group-title">👥 Account & Support</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Up to 10 Users</li>
                            <li><span class="checkmark">✓</span> Support Ticket System</li>
                            <li><span class="checkmark">✓</span> 14-day Free Trial</li>
                        </ul>
                    </div>

                    <div class="plan-ideal">
                        👉 Ideal for agencies starting digital operations.
                    </div>
                </div>

                <!-- Umrah Plan -->
                <div class="pricing-card">
                    <div class="plan-badge umrah-badge">🕌 Umrah</div>
                    <div class="plan-header">
                        <h3>Umrah Plan</h3>
                        <p class="plan-subtitle">Best for Umrah-only agencies</p>
                    </div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">2,000</span>
                        <span class="period">/month</span>
                        <span class="annual-price" style="display: none;">AFN 19,200/year</span>
                    </div>
                    <p class="plan-description">Built specifically for Umrah-focused businesses that manage families, payments, and agreements with full compliance.</p>
                    
                    <button class="btn btn-outline btn-block" onclick="window.location.href='book-demo.php'">Get Started</button>
                    <button class="btn btn-secondary btn-block" onclick="window.location.href='login.php?plan=umrah'">Start Free Trial</button>

                    <div class="features-section">
                        <h4>What's Included:</h4>
                        <div class="features-group">
                            <div class="feature-group-title">🕌 Umrah Services</div>
                            <ul class="features-list">
                                <li><span class="checkmark">✓</span> Family Management</li>
                                <li><span class="checkmark">✓</span> Member Management</li>
                                <li><span class="checkmark">✓</span> ID Card Generation</li>
                                <li><span class="checkmark">✓</span> Agreement Generation</li>
                                <li><span class="checkmark">✓</span> Cancellation & Refund Processing</li>
                            </ul>
                        </div>

                        <div class="feature-group-title">💳 Payments & Finance</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Payment Processing</li>
                            <li><span class="checkmark">✓</span> Multi-Currency Support</li>
                            <li><span class="checkmark">✓</span> Financial Management</li>
                            <li><span class="checkmark">✓</span> Financial Statements</li>
                        </ul>

                        <div class="feature-group-title">🏢 Business Operations</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Expense Management</li>
                            <li><span class="checkmark">✓</span> Operational Tracking</li>
                        </ul>

                        <div class="feature-group-title">💬 Communication</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Inter-Tenant Chat</li>
                            <li><span class="checkmark">✓</span> Email Notifications</li>
                        </ul>

                        <div class="feature-group-title">👥 Account & Support</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Up to 10 Users</li>
                            <li><span class="checkmark">✓</span> Support Ticket System</li>
                            <li><span class="checkmark">✓</span> 14-day Free Trial</li>
                        </ul>
                    </div>

                    <div class="plan-ideal">
                        👉 Perfect for Umrah agencies needing structure & compliance.
                    </div>
                </div>

                <!-- Pro Plan (Highlighted) -->
                <div class="pricing-card featured-card">
                    <div class="plan-badge pro-badge">⭐ Pro (Most Popular)</div>
                    <div class="plan-header">
                        <h3>Pro Plan</h3>
                        <p class="plan-subtitle">Best for growing multi-service agencies</p>
                    </div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">2,500</span>
                        <span class="period">/month</span>
                        <span class="annual-price" style="display: none;">AFN 24,000/year</span>
                    </div>
                    <p class="plan-description">A complete solution for agencies selling tickets, hotels, and visas with deeper financial workflows.</p>
                    
                    <button class="btn btn-primary btn-block" onclick="window.location.href='book-demo.php'">Get Started Now</button>
                    <button class="btn btn-secondary btn-block" onclick="window.location.href='login.php?plan=pro'">Start Free Trial</button>

                    <div class="features-section">
                        <h4>Everything in Basic, plus:</h4>
                        <div class="features-group">
                            <div class="feature-group-title">🏨 Hotel Services</div>
                            <ul class="features-list">
                                <li><span class="checkmark">✓</span> Hotel Bookings</li>
                                <li><span class="checkmark">✓</span> Hotel Refunds & Management</li>
                            </ul>
                        </div>

                        <div class="feature-group-title">🛂 Visa Services</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Visa Applications</li>
                            <li><span class="checkmark">✓</span> Visa Refunds</li>
                            <li><span class="checkmark">✓</span> Visa Transactions</li>
                        </ul>

                        <div class="feature-group-title">💼 Advanced Finance</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Additional Payments</li>
                            <li><span class="checkmark">✓</span> Linked Financial Records</li>
                        </ul>

                        <div class="feature-group-title">👥 Account & Support</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Up to 15 Users (5 more)</li>
                            <li><span class="checkmark">✓</span> Priority Support</li>
                            <li><span class="checkmark">✓</span> 14-day Free Trial</li>
                        </ul>
                    </div>

                    <div class="plan-ideal">
                        👉 Best for agencies expanding beyond tickets.
                    </div>
                </div>

                <!-- Enterprise Plan -->
                <div class="pricing-card">
                    <div class="plan-badge enterprise-badge">🚀 Enterprise</div>
                    <div class="plan-header">
                        <h3>Enterprise Plan</h3>
                        <p class="plan-subtitle">Best for large multi-branch operations</p>
                    </div>
                    <div class="plan-price">
                        <span class="currency">AFN</span>
                        <span class="amount monthly-price">4,500</span>
                        <span class="period">/month</span>
                        <span class="annual-price" style="display: none;">AFN 43,200/year</span>
                    </div>
                    <p class="plan-description">Built for agencies that need full control, automation, and reporting across branches with enterprise-grade features.</p>
                    
                    <button class="btn btn-outline btn-block" onclick="window.location.href='book-demo.php'">Get Started</button>
                    <button class="btn btn-secondary btn-block" onclick="window.location.href='login.php?plan=enterprise'">Start Free Trial</button>

                    <div class="features-section">
                        <h4>Everything in Pro, plus:</h4>
                        <div class="features-group">
                            <div class="feature-group-title">🕌 Advanced Umrah Management</div>
                            <ul class="features-list">
                                <li><span class="checkmark">✓</span> Family & Member Management</li>
                                <li><span class="checkmark">✓</span> ID Cards & Agreements</li>
                                <li><span class="checkmark">✓</span> Cancellations & Refunds</li>
                            </ul>
                        </div>

                        <div class="feature-group-title">💳 Advanced Payment Processing</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Full Multi-Currency Handling</li>
                            <li><span class="checkmark">✓</span> Debtors & Creditors</li>
                            <li><span class="checkmark">✓</span> Sarafi Management</li>
                        </ul>

                        <div class="feature-group-title">💼 Advanced Financial Suite</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Salary Management</li>
                            <li><span class="checkmark">✓</span> JV Payments</li>
                            <li><span class="checkmark">✓</span> Full Financial Statements</li>
                        </ul>

                        <div class="feature-group-title">🏢 Business Operations</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Maktob Management</li>
                            <li><span class="checkmark">✓</span> Asset Management</li>
                        </ul>

                        <div class="feature-group-title">🏆 Enterprise Access</div>
                        <ul class="features-list">
                            <li><span class="checkmark">✓</span> Up to 30 Users (15 more)</li>
                            <li><span class="checkmark">✓</span> Tenant Super Admin Dashboard</li>
                            <li><span class="checkmark">✓</span> Branch-wise Performance</li>
                            <li><span class="checkmark">✓</span> Exportable Reports</li>
                            <li><span class="checkmark">✓</span> 14-day Free Trial</li>
                        </ul>
                    </div>

                    <div class="plan-ideal">
                        👉 Designed for high-volume, multi-branch agencies.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Included in All Plans Section -->
    <section class="included-section">
        <div class="container">
            <h2>✨ Included in All Plans</h2>
            <div class="included-grid">
                <div class="included-item">
                    <div class="included-icon">🔒</div>
                    <h4>Secure Cloud System</h4>
                    <p>Enterprise-grade security with AES-256 encryption</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">📧</div>
                    <h4>Email Automation</h4>
                    <p>Automated notifications & workflows</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">💬</div>
                    <h4>WhatsApp Automation</h4>
                    <p>Tenant-managed messaging system</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">🧠</div>
                    <h4>OCR Technology</h4>
                    <p>Auto-fill tickets & passport data</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">📊</div>
                    <h4>Interactive Dashboards</h4>
                    <p>Real-time insights & analytics</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">🔐</div>
                    <h4>Audit Logs</h4>
                    <p>Complete change history & compliance</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">👥</div>
                    <h4>Role-Based Access</h4>
                    <p>Granular permission management</p>
                </div>
                <div class="included-item">
                    <div class="included-icon">🌍</div>
                    <h4>Multi-Currency</h4>
                    <p>Support for AFN, USD, EUR & more</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Comparison Table Section -->
    <section class="comparison-section">
        <div class="container">
            <h2>Feature Comparison</h2>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th class="feature-col">Feature</th>
                            <th>Basic</th>
                            <th>Umrah</th>
                            <th>Pro</th>
                            <th>Enterprise</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="feature-name">Users</td>
                            <td><span class="check">✓</span> 10</td>
                            <td><span class="check">✓</span> 10</td>
                            <td><span class="check">✓</span> 15</td>
                            <td><span class="check">✓</span> 30</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Ticket Management</td>
                            <td><span class="check">✓</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Umrah Management</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span> Advanced</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Hotel Services</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Visa Services</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Financial Statements</td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span> Advanced</td>
                        </tr>
                        <tr>
                            <td class="feature-name">Debtors & Creditors</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Sarafi Management</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Salary Management</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Priority Support</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Super Admin Dashboard</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                        <tr>
                            <td class="feature-name">Branch Performance Reports</td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="times">✗</span></td>
                            <td><span class="check">✓</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            <div class="faq-grid">
                <div class="faq-item">
                    <h4>Can I upgrade or downgrade my plan anytime?</h4>
                    <p>Yes! You can change your plan at any time. If you upgrade, you'll be charged the difference. If you downgrade, you'll receive a credit for your next billing cycle.</p>
                </div>
                <div class="faq-item">
                    <h4>What payment methods do you accept?</h4>
                    <p>We accept bank transfers, credit/debit cards, and local payment methods. Contact our sales team for invoicing and custom payment arrangements.</p>
                </div>
                <div class="faq-item">
                    <h4>Is there a setup fee?</h4>
                    <p>No hidden fees! The price you see is what you pay. All plans include free onboarding support and setup assistance.</p>
                </div>
                <div class="faq-item">
                    <h4>Do you offer annual discounts?</h4>
                    <p>Yes! Switch to annual billing and save 20% on any plan. Enterprise customers can negotiate custom pricing based on their needs.</p>
                </div>
                <div class="faq-item">
                    <h4>What if I exceed my user limit?</h4>
                    <p>You can add additional users anytime at a pro-rata cost. Simply request more users in your account settings or contact our support team.</p>
                </div>
                <div class="faq-item">
                    <h4>Is data secure and backed up?</h4>
                    <p>Absolutely. All data is encrypted with AES-256, backed up daily, and compliant with GDPR and international security standards. We maintain 99.9% uptime.</p>
                </div>
                <div class="faq-item">
                    <h4>Can I customize the Pro or Enterprise plans?</h4>
                    <p>Yes! Larger organizations can customize features, workflows, and integrations. Contact our enterprise team for a personalized quote.</p>
                </div>
                <div class="faq-item">
                    <h4>What happens after my free trial?</h4>
                    <p>After 14 days, you can start a paid subscription. We'll never charge without your consent. You can cancel anytime with no penalties.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="pricing-cta">
        <div class="container">
            <h2>Not Sure Which Plan is Right for You?</h2>
            <p>Start with a 14-day free trial—no credit card required. Or let our team recommend the perfect plan based on your business.</p>
            <div class="cta-buttons">
                <a href="login.php" class="btn btn-primary btn-lg">Start Free Trial</a>
                <a href="book-demo.php" class="btn btn-outline btn-lg">Talk to Sales</a>
            </div>
        </div>
    </section>

    <?php require_once 'includes/footer.php'; ?>

    <script>
        // Billing Toggle Functionality
        const billingToggle = document.getElementById('billingToggle');
        
        billingToggle.addEventListener('change', function() {
            const monthlyPrices = document.querySelectorAll('.monthly-price');
            const annualPrices = document.querySelectorAll('.annual-price');
            
            monthlyPrices.forEach(price => {
                price.style.display = this.checked ? 'none' : 'inline';
            });
            
            annualPrices.forEach(price => {
                price.style.display = this.checked ? 'inline' : 'none';
            });
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });
    </script>
    <script>
    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.querySelector('.nav-menu');

        if (hamburger && navMenu) {
            hamburger.addEventListener('click', function() {
                navMenu.classList.toggle('open');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!hamburger.contains(event.target) && !navMenu.contains(event.target)) {
                    navMenu.classList.remove('open');
                }
            });
        }
    });
</script>
    <?php renderThemeScript(); ?>
</body>
</html>
