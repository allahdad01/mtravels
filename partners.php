<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/cache.php';

// Default tenant ID for landing page (can be made configurable)
$default_tenant_id = 1;

// Optimized function to fetch platform settings with caching
function getPlatformSettings($pdo) {
    $cache_key = getCacheKey('platform_settings');

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }

        setCachedData($cache_key, $settings);
        return $settings;
    } catch (PDOException $e) {
        error_log("Error fetching platform settings: " . $e->getMessage());
        return [];
    }
}

// Helper function to get setting value
function getSetting($settings, $key, $default = '') {
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}

// Fetch platform settings
$platform_settings = getPlatformSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partners - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Join our partner ecosystem. Become a MTravels partner and grow your business with our comprehensive travel technology platform.">
        <!-- Favicon -->
        <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4099ff;
            --primary-dark: #2673cc;
            --primary-light: #a0e6ff;
            --secondary: #2ed8b6;
            --secondary-dark: #24a88f;
            --secondary-light: #8ef0e0;
            --accent: #25c6b4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --light: #f8fafc;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--gray-800);
            background: var(--white);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Advanced Navbar */
        .navbar {
            position: fixed;
            top: 30px;
            left: 100px;
            right: 100px;
            padding: 1.5rem 2rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            border-radius: 50px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo img {
            max-height: 40px;
            width: auto;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--primary);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 600;
            position: relative;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
            color: #ffffff !important;
            border-bottom: none !important;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.4);
        }

        /* Hero Section */
        .hero {
            padding: 12rem 0 8rem;
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            text-align: center;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            line-height: 1.1;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.9;
        }

        /* Partner Content */
        .partner-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Partner Types */
        .partner-types {
            margin-bottom: 6rem;
        }

        .partner-types h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .types-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .partner-type-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .partner-type-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .partner-type-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .partner-type-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .partner-type-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .partner-type-benefits {
            text-align: left;
        }

        .partner-type-benefits h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .partner-type-benefits ul {
            list-style: none;
            padding: 0;
        }

        .partner-type-benefits li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--gray-600);
        }

        .partner-type-benefits li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        /* Current Partners */
        .current-partners {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .current-partners h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 1rem;
        }

        .partners-subtitle {
            font-size: 1.1rem;
            color: var(--gray-600);
            text-align: center;
            margin-bottom: 4rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .partners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            align-items: stretch;
        }

        .partner-logo {
            background: var(--white);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 2px solid var(--gray-100);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 160px;
        }

        .partner-logo:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.15);
            border-color: var(--primary);
        }

        .partner-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .partner-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .partner-description {
            font-size: 0.9rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .partner-logo img {
            max-width: 100%;
            max-height: 80px;
            object-fit: contain;
        }

        /* Partner Benefits */
        .partner-benefits {
            margin-bottom: 6rem;
        }

        .partner-benefits h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .benefit-item {
            text-align: center;
            padding: 2rem;
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .benefit-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.15);
        }

        .benefit-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .benefit-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .benefit-description {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Become a Partner */
        .become-partner {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .become-partner h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .become-partner p {
            font-size: 1.1rem;
            color: var(--gray-600);
            text-align: center;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .partner-application {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            max-width: 600px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 2px solid var(--gray-100);
        }

        .application-form {
            display: grid;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* CTA Section */
        .partner-cta {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 6rem 0;
            text-align: center;
            color: white;
        }

        .partner-cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin-bottom: 1.5rem;
        }

        .partner-cta p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .partner-cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: var(--gray-50);
            color: var(--gray-900);
            padding: 4rem 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--gray-900);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section li {
            margin-bottom: 0.8rem;
        }

        .footer-section a {
            color: var(--gray-600);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            padding-top: 2rem;
            border-top: 1px solid var(--primary);
            text-align: center;
            color: var(--gray-600);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                left: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .types-grid,
            .partners-grid,
            .benefits-grid {
                grid-template-columns: 1fr;
            }

            .partners-subtitle {
                font-size: 1rem;
                margin-bottom: 3rem;
            }

            .partner-logo {
                padding: 2rem 1.5rem;
                min-height: 140px;
            }

            .partner-icon {
                font-size: 2rem;
            }

            .partner-name {
                font-size: 1.1rem;
            }

            .partner-description {
                font-size: 0.85rem;
            }

            .partner-application {
                padding: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .partner-cta h2 {
                font-size: 2rem;
            }

            .partner-cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .partner-cta {
                padding: 4rem 0;
            }

            .partner-cta h2 {
                font-size: 2.5rem;
            }

            .partner-cta p {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="index.php" class="logo">
                    <img src="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'logo.png') ?>" alt="Logo" style="height: 40px;">
                    <span class="logo-text"><?= htmlspecialchars(getSetting($platform_settings, 'platform_name') ?? 'MTravels') ?></span>
                </a>
                <div class="nav-menu">
                    <ul class="nav-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#pricing">Pricing</a></li>
                        <li><a href="about.php">About</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                    </ul>
                    <div class="nav-actions">
                        <a href="login.php" class="nav-login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.3s;">Login</a>
                        <a href="book-demo.php" class="btn btn-primary">
                            <span>Book a Demo</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Partner With Us</h1>
            <p>Join our growing ecosystem of partners and unlock new opportunities in the travel technology industry. Together, we can deliver exceptional value to travel agencies worldwide.</p>
        </div>
    </section>

    <!-- Partner Content -->
    <section class="partner-content">
        <div class="container">
            <!-- Partner Types -->
            <div class="partner-types">
                <h2>Partner Programs</h2>
                <div class="types-grid">
                    <div class="partner-type-card">
                        <div class="partner-type-icon">🤝</div>
                        <h3 class="partner-type-title">Technology Partners</h3>
                        <p class="partner-type-description">Integrate your technology solutions with MTravels platform to create seamless workflows and enhanced user experiences.</p>
                        <div class="partner-type-benefits">
                            <h4>Benefits:</h4>
                            <ul>
                                <li>API access and integration support</li>
                                <li>Co-marketing opportunities</li>
                                <li>Technical documentation and resources</li>
                                <li>Revenue sharing opportunities</li>
                            </ul>
                        </div>
                    </div>

                    <div class="partner-type-card">
                        <div class="partner-type-icon">🏢</div>
                        <h3 class="partner-type-title">Reseller Partners</h3>
                        <p class="partner-type-description">Sell MTravels solutions to your clients and earn commissions on every successful sale and renewal.</p>
                        <div class="partner-type-benefits">
                            <h4>Benefits:</h4>
                            <ul>
                                <li>Competitive commission rates</li>
                                <li>Dedicated partner portal</li>
                                <li>Sales and marketing support</li>
                                <li>Training and certification programs</li>
                            </ul>
                        </div>
                    </div>

                    <div class="partner-type-card">
                        <div class="partner-type-icon">🎓</div>
                        <h3 class="partner-type-title">Training Partners</h3>
                        <p class="partner-type-description">Deliver MTravels training programs and certification courses to travel professionals and agencies.</p>
                        <div class="partner-type-benefits">
                            <h4>Benefits:</h4>
                            <ul>
                                <li>Official training materials</li>
                                <li>Certification programs</li>
                                <li>Marketing and lead generation support</li>
                                <li>Ongoing training updates</li>
                            </ul>
                        </div>
                    </div>

                    <div class="partner-type-card">
                        <div class="partner-type-icon">🌐</div>
                        <h3 class="partner-type-title">Referral Partners</h3>
                        <p class="partner-type-description">Earn commissions by referring new clients to MTravels. Perfect for consultants, agencies, and industry influencers.</p>
                        <div class="partner-type-benefits">
                            <h4>Benefits:</h4>
                            <ul>
                                <li>Generous referral commissions</li>
                                <li>Marketing materials and tools</li>
                                <li>Dedicated partner manager</li>
                                <li>Real-time tracking and reporting</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Partners -->
            <div class="current-partners">
                <h2>Trusted Partners</h2>
                <p class="partners-subtitle">We collaborate with industry leaders to deliver exceptional travel solutions</p>
                <div class="partners-grid">
                    <div class="partner-logo">
                        <div class="partner-icon">✈️</div>
                        <div class="partner-name">Amadeus</div>
                        <div class="partner-description">Global Distribution System</div>
                    </div>
                    <div class="partner-logo">
                        <div class="partner-icon">🏨</div>
                        <div class="partner-name">Booking.com</div>
                        <div class="partner-description">Hotel Booking Platform</div>
                    </div>
                    <div class="partner-logo">
                        <div class="partner-icon">💳</div>
                        <div class="partner-name">Stripe</div>
                        <div class="partner-description">Payment Processing</div>
                    </div>
                    <div class="partner-logo">
                        <div class="partner-icon">📧</div>
                        <div class="partner-name">Mailchimp</div>
                        <div class="partner-description">Email Marketing</div>
                    </div>
                    <div class="partner-logo">
                        <div class="partner-icon">🔗</div>
                        <div class="partner-name">Zapier</div>
                        <div class="partner-description">Automation Platform</div>
                    </div>
                    <div class="partner-logo">
                        <div class="partner-icon">📊</div>
                        <div class="partner-name">Google Analytics</div>
                        <div class="partner-description">Web Analytics</div>
                    </div>
                </div>
            </div>

            <!-- Partner Benefits -->
            <div class="partner-benefits">
                <h2>Why Partner With Us?</h2>
                <div class="benefits-grid">
                    <div class="benefit-item">
                        <div class="benefit-icon">📈</div>
                        <h3 class="benefit-title">Revenue Growth</h3>
                        <p class="benefit-description">Access new revenue streams through commissions, licensing fees, and co-selling opportunities with our established client base.</p>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">🚀</div>
                        <h3 class="benefit-title">Technology Advantage</h3>
                        <p class="benefit-description">Leverage our cutting-edge technology platform to enhance your offerings and stay ahead of industry trends.</p>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">🤝</div>
                        <h3 class="benefit-title">Strategic Alliance</h3>
                        <p class="benefit-description">Build strategic relationships with a market-leading travel technology company and expand your business network.</p>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">🎯</div>
                        <h3 class="benefit-title">Market Expansion</h3>
                        <p class="benefit-description">Reach new markets and customer segments through our global presence and extensive travel industry connections.</p>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">🛠️</div>
                        <h3 class="benefit-title">Technical Support</h3>
                        <p class="benefit-description">Receive dedicated technical support, training, and resources to ensure successful partnership implementation.</p>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon">📊</div>
                        <h3 class="benefit-title">Performance Tracking</h3>
                        <p class="benefit-description">Monitor partnership performance with detailed analytics, reporting, and optimization recommendations.</p>
                    </div>
                </div>
            </div>

            <!-- Become a Partner -->
            <div class="become-partner">
                <h2>Become a Partner Today</h2>
                <p>Ready to join our partner ecosystem? Fill out the form below and our partnership team will contact you within 24 hours.</p>

                <div class="partner-application">
                    <form class="application-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName">First Name *</label>
                                <input type="text" id="firstName" name="firstName" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName">Last Name *</label>
                                <input type="text" id="lastName" name="lastName" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="company">Company Name *</label>
                            <input type="text" id="company" name="company" required>
                        </div>

                        <div class="form-group">
                            <label for="partnerType">Partnership Type *</label>
                            <select id="partnerType" name="partnerType" required>
                                <option value="">Select Partnership Type</option>
                                <option value="technology">Technology Partner</option>
                                <option value="reseller">Reseller Partner</option>
                                <option value="training">Training Partner</option>
                                <option value="referral">Referral Partner</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="website">Website</label>
                            <input type="url" id="website" name="website" placeholder="https://">
                        </div>

                        <div class="form-group">
                            <label for="message">Tell us about your partnership goals *</label>
                            <textarea id="message" name="message" rows="4" placeholder="Describe your company, target market, and how you see this partnership benefiting both parties..." required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%;">Submit Partnership Application</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="partner-cta">
        <div class="container">
            <h2>Questions About Partnership?</h2>
            <p>Our partnership team is here to help you understand the opportunities and answer any questions you may have.</p>
            <div class="partner-cta-buttons">
                <a href="mailto:partners@mtravels.com" class="btn btn-primary">Contact Partnership Team</a>
                <a href="index.php#contact" class="btn" style="background: transparent; color: white; border: 2px solid white;">Schedule a Call</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></h3>
                    <p style="color: var(--gray-300); line-height: 1.6;">
                        <?php echo getSetting($platform_settings, 'platform_description', 'Professional travel agency management platform providing comprehensive solutions for booking management, financial operations, customer service, and business intelligence.'); ?>
                    </p>
                </div>
                <div class="footer-section">
                    <h3>Product</h3>
                    <ul>
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#pricing">Pricing</a></li>
                        <li><a href="integrations.php">Integrations</a></li>
                        <li><a href="api-docs.php">API Documentation</a></li>
                        <li><a href="security.php">Security</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="careers.php">Careers</a></li>
                        <li><a href="press.php">Press</a></li>
                        <li><a href="blog.php">Blog</a></li>
                        <li><a href="partners.php">Partners</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="help.php">Help Center</a></li>
                        <li><a href="index.php#contact">Contact Support</a></li>
                        <li><a href="status.php">System Status</a></li>
                        <li><a href="community.php">Community</a></li>
                        <li><a href="training.php">Training</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
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

        // Partnership application form
        document.querySelector('.application-form').addEventListener('submit', function(e) {
            e.preventDefault();

            // Simple form validation
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--danger)';
                    isValid = false;
                } else {
                    field.style.borderColor = 'var(--gray-200)';
                }
            });

            if (isValid) {
                alert('Thank you for your partnership application! Our team will review your submission and contact you within 24 hours.');
                this.reset();
            } else {
                alert('Please fill in all required fields.');
            }
        });

        // Real-time validation
        document.querySelectorAll('.application-form input, .application-form select, .application-form textarea').forEach(field => {
            field.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = 'var(--gray-200)';
                }
            });
        });
    </script>
</body>
</html>