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
    <title>Integrations - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Connect MTravels with your favorite tools and services. Explore our comprehensive integration ecosystem for travel agencies.">
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

        /* Integration Content */
        .integration-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Integration Categories */
        .integration-categories {
            margin-bottom: 6rem;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .category-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .category-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .category-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .category-description {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Featured Integrations */
        .featured-integrations {
            margin-bottom: 6rem;
        }

        .integrations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .integration-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .integration-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .integration-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .integration-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .integration-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .integration-features {
            text-align: left;
            margin-bottom: 2rem;
        }

        .integration-features h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .integration-features ul {
            list-style: none;
            padding: 0;
        }

        .integration-features li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--gray-600);
        }

        .integration-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        .integration-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--success);
            color: white;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        /* API Section */
        .api-section {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .api-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .api-text h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .api-text p {
            font-size: 1.1rem;
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .api-features {
            margin-bottom: 2rem;
        }

        .api-features h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .api-features ul {
            list-style: none;
            padding: 0;
        }

        .api-features li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--gray-600);
        }

        .api-features li::before {
            content: '🚀';
            position: absolute;
            left: 0;
        }

        .api-image {
            position: relative;
        }

        .api-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        /* CTA Section */
        .integration-cta {
            background: var(--gray-50);
            padding: 6rem 0;
            text-align: center;
        }

        .integration-cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .integration-cta p {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .integration-cta-buttons {
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

            .categories-grid,
            .integrations-grid {
                grid-template-columns: 1fr;
            }

            .api-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .api-text h2 {
                font-size: 2rem;
            }

            .integration-cta h2 {
                font-size: 2rem;
            }

            .integration-cta-buttons {
                flex-direction: column;
                align-items: center;
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
            <h1>Powerful Integrations</h1>
            <p>Connect MTravels with your favorite tools and services to create a seamless workflow that boosts productivity and enhances customer experience.</p>
        </div>
    </section>

    <!-- Integration Content -->
    <section class="integration-content">
        <div class="container">
            <!-- Integration Categories -->
            <div class="integration-categories">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Integration Categories</h2>

                <div class="categories-grid">
                    <div class="category-card">
                        <div class="category-icon">✈️</div>
                        <h3 class="category-title">Airline & GDS</h3>
                        <p class="category-description">Connect with major airlines, GDS systems, and booking engines for real-time flight availability and pricing.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">🏨</div>
                        <h3 class="category-title">Hotel & Accommodation</h3>
                        <p class="category-description">Integrate with hotel booking platforms, property management systems, and vacation rental services.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">🚗</div>
                        <h3 class="category-title">Transportation</h3>
                        <p class="category-description">Connect with car rental companies, ride-sharing services, and transportation booking platforms.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">💳</div>
                        <h3 class="category-title">Payment & Finance</h3>
                        <p class="category-description">Integrate with payment gateways, banking systems, and financial management tools.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">📧</div>
                        <h3 class="category-title">Communication</h3>
                        <p class="category-description">Connect with email marketing, CRM systems, and customer communication platforms.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">📊</div>
                        <h3 class="category-title">Analytics & Reporting</h3>
                        <p class="category-description">Integrate with business intelligence tools, reporting platforms, and analytics services.</p>
                    </div>
                </div>
            </div>

            <!-- Featured Integrations -->
            <div class="featured-integrations">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Featured Integrations</h2>

                <div class="integrations-grid">
                    <div class="integration-card">
                        <div class="integration-logo">🛫</div>
                        <h3 class="integration-title">Amadeus GDS</h3>
                        <p class="integration-description">Complete integration with Amadeus Global Distribution System for comprehensive flight booking and management.</p>
                        <div class="integration-status">Available</div>
                        <div class="integration-features">
                            <h4>Key Features:</h4>
                            <ul>
                                <li>Real-time flight availability</li>
                                <li>Automated booking confirmation</li>
                                <li>Fare comparison and optimization</li>
                                <li>PNR management and updates</li>
                            </ul>
                        </div>
                    </div>

                    <div class="integration-card">
                        <div class="integration-logo">🏨</div>
                        <h3 class="integration-title">Booking.com API</h3>
                        <p class="integration-description">Direct integration with Booking.com for hotel inventory management and reservation synchronization.</p>
                        <div class="integration-status">Available</div>
                        <div class="integration-features">
                            <h4>Key Features:</h4>
                            <ul>
                                <li>Real-time inventory sync</li>
                                <li>Automated rate updates</li>
                                <li>Reservation management</li>
                                <li>Revenue optimization</li>
                            </ul>
                        </div>
                    </div>

                    <div class="integration-card">
                        <div class="integration-logo">💳</div>
                        <h3 class="integration-title">Stripe Payments</h3>
                        <p class="integration-description">Secure payment processing with Stripe for all your travel agency transactions and customer payments.</p>
                        <div class="integration-status">Available</div>
                        <div class="integration-features">
                            <h4>Key Features:</h4>
                            <ul>
                                <li>PCI DSS compliant</li>
                                <li>Multi-currency support</li>
                                <li>Automated invoicing</li>
                                <li>Fraud protection</li>
                            </ul>
                        </div>
                    </div>

                    <div class="integration-card">
                        <div class="integration-logo">📧</div>
                        <h3 class="integration-title">Mailchimp</h3>
                        <p class="integration-description">Email marketing integration for customer newsletters, promotional campaigns, and automated communications.</p>
                        <div class="integration-status">Available</div>
                        <div class="integration-features">
                            <h4>Key Features:</h4>
                            <ul>
                                <li>Customer segmentation</li>
                                <li>Automated email campaigns</li>
                                <li>Performance analytics</li>
                                <li>Audience management</li>
                            </ul>
                        </div>
                    </div>

                    <div class="integration-card">
                        <div class="integration-logo">📱</div>
                        <h3 class="integration-title">Zapier</h3>
                        <p class="integration-description">Connect MTravels with 3,000+ apps through Zapier for custom automation and workflow optimization.</p>
                        <div class="integration-status">Available</div>
                        <div class="integration-features">
                            <h4>Key Features:</h4>
                            <ul>
                                <li>Custom workflow automation</li>
                                <li>3,000+ app integrations</li>
                                <li>No-code automation</li>
                                <li>Real-time data sync</li>
                            </ul>
                        </div>
                    </div>

                    <div class="integration-card">
                        <div class="integration-logo">📊</div>
                        <h3 class="integration-title">Google Analytics</h3>
                        <p class="integration-description">Advanced analytics integration to track website performance, customer behavior, and conversion metrics.</p>
                        <div class="integration-status">Available</div>
                        <div class="integration-features">
                            <h4>Key Features:</h4>
                            <ul>
                                <li>Traffic analysis</li>
                                <li>Conversion tracking</li>
                                <li>Customer journey mapping</li>
                                <li>Performance reporting</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Section -->
            <div class="api-section">
                <div class="container">
                    <div class="api-content">
                        <div class="api-text">
                            <h2>Developer-Friendly API</h2>
                            <p>Build custom integrations and extend MTravels functionality with our comprehensive REST API. Access all platform features programmatically.</p>
                            <div class="api-features">
                                <h3>API Capabilities:</h3>
                                <ul>
                                    <li>RESTful API with JSON responses</li>
                                    <li>OAuth 2.0 authentication</li>
                                    <li>Webhook support for real-time updates</li>
                                    <li>Comprehensive documentation</li>
                                    <li>SDKs for popular programming languages</li>
                                    <li>Rate limiting and usage analytics</li>
                                </ul>
                            </div>
                            <a href="api-docs.php" class="btn btn-primary">View API Documentation</a>
                        </div>
                        <div class="api-image">
                            <img src="assets/images/widget/undraw_finance_m6vw.svg" alt="API Integration" style="max-width: 100%; height: auto;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="integration-cta">
        <div class="container">
            <h2>Ready to Integrate?</h2>
            <p>Join hundreds of travel agencies who have streamlined their operations with our powerful integration ecosystem.</p>
            <div class="integration-cta-buttons">
                <a href="book-demo.php" class="btn btn-primary">Request Integration</a>
                <a href="api-docs.php" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">API Documentation</a>
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

        // Category click functionality
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                const category = this.querySelector('.category-title').textContent;
                alert('Integration category: ' + category + ' - Coming soon!');
            });
        });
    </script>
</body>
</html>