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
    <title>Help Center - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Find answers to common questions and get help with MTravels platform. Comprehensive guides, tutorials, and support resources.">
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

        /* Help Content */
        .help-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Search Section */
        .help-search {
            text-align: center;
            margin-bottom: 4rem;
        }

        .search-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1.2rem 3rem 1.2rem 1.5rem;
            border: 3px solid var(--gray-200);
            border-radius: 50px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(64, 153, 255, 0.1);
        }

        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-50%) scale(1.05);
        }

        /* Categories Section */
        .help-categories {
            margin-bottom: 4rem;
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

        /* FAQ Section */
        .faq-section {
            margin-bottom: 4rem;
        }

        .faq-item {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 15px;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-question {
            width: 100%;
            padding: 1.5rem 2rem;
            background: none;
            border: none;
            text-align: left;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: var(--gray-50);
        }

        .faq-toggle {
            font-size: 1.5rem;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .faq-item.active .faq-toggle {
            transform: rotate(45deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--gray-50);
        }

        .faq-item.active .faq-answer {
            max-height: 500px;
        }

        .faq-content {
            padding: 0 2rem 2rem;
            color: var(--gray-600);
            line-height: 1.7;
        }

        /* Contact Support */
        .contact-support {
            background: var(--gray-50);
            padding: 4rem 0;
            text-align: center;
            border-radius: 20px;
            margin: 4rem 0;
        }

        .contact-support h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .contact-support p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .contact-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .contact-option {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .contact-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(64, 153, 255, 0.1);
        }

        .contact-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .contact-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .contact-description {
            color: var(--gray-600);
            margin-bottom: 1rem;
        }

        .contact-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .contact-link:hover {
            gap: 1rem;
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

            .categories-grid {
                grid-template-columns: 1fr;
            }

            .contact-options {
                grid-template-columns: 1fr;
            }

            .contact-support h2 {
                font-size: 2rem;
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
            <h1>Help Center</h1>
            <p>Find answers to your questions and get the help you need to make the most of <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?>.</p>
        </div>
    </section>

    <!-- Help Content -->
    <section class="help-content">
        <div class="container">
            <!-- Search Section -->
            <div class="help-search">
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="Search for help articles, guides, and FAQs...">
                    <button class="search-btn">🔍</button>
                </div>
            </div>

            <!-- Categories Section -->
            <div class="help-categories">
                <div class="categories-grid">
                    <div class="category-card">
                        <div class="category-icon">🚀</div>
                        <h3 class="category-title">Getting Started</h3>
                        <p class="category-description">Learn the basics of using MTravels platform, from account setup to your first booking.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">💰</div>
                        <h3 class="category-title">Billing & Payments</h3>
                        <p class="category-description">Understand pricing, billing cycles, payment methods, and subscription management.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">📊</div>
                        <h3 class="category-title">Reports & Analytics</h3>
                        <p class="category-description">Generate reports, analyze performance data, and track business metrics.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">🔧</div>
                        <h3 class="category-title">Troubleshooting</h3>
                        <p class="category-description">Common issues and solutions for technical problems and system errors.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">👥</div>
                        <h3 class="category-title">Account Management</h3>
                        <p class="category-description">Manage users, permissions, security settings, and account preferences.</p>
                    </div>
                    <div class="category-card">
                        <div class="category-icon">📱</div>
                        <h3 class="category-title">Integrations</h3>
                        <p class="category-description">Connect with third-party services, APIs, and external systems.</p>
                    </div>
                </div>
            </div>

            <!-- FAQ Section -->
            <div class="faq-section">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 3rem;">Frequently Asked Questions</h2>

                <div class="faq-item">
                    <button class="faq-question">
                        How do I get started with MTravels?
                        <span class="faq-toggle">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-content">
                            Getting started is easy! Simply sign up for an account, choose your plan, and complete the onboarding process. Our team will guide you through setting up your agency profile, configuring basic settings, and importing your existing data. You'll be up and running within minutes.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        What payment methods do you accept?
                        <span class="faq-toggle">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-content">
                            We accept all major credit cards (Visa, MasterCard, American Express), PayPal, bank transfers, and various local payment methods depending on your region. All payments are processed securely through our PCI-compliant payment gateway.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        Can I cancel my subscription anytime?
                        <span class="faq-toggle">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-content">
                            Yes, you can cancel your subscription at any time. Your account will remain active until the end of your current billing period. You can reactivate your account at any time during this period if you change your mind.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        Is my data secure?
                        <span class="faq-toggle">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-content">
                            Absolutely. We employ bank-level security measures including 256-bit SSL encryption, regular security audits, and compliance with international data protection standards. Your customer data and business information are protected with multiple layers of security.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        Do you offer training and support?
                        <span class="faq-toggle">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-content">
                            Yes! We provide comprehensive training through our help center, video tutorials, documentation, and live webinars. Our support team is available 24/7 via email, chat, and phone to assist you with any questions or issues.
                        </div>
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        Can I migrate data from my current system?
                        <span class="faq-toggle">+</span>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-content">
                            Yes, we offer data migration services to help you transfer your existing customer data, bookings, and historical records. Our migration specialists will work with you to ensure a smooth transition with minimal disruption to your operations.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Support -->
            <div class="contact-support">
                <h2>Still Need Help?</h2>
                <p>Our support team is here to help you succeed. Choose the best way to reach us.</p>
                <div class="contact-options">
                    <div class="contact-option">
                        <div class="contact-icon">💬</div>
                        <h3 class="contact-title">Live Chat</h3>
                        <p class="contact-description">Get instant help from our support team during business hours.</p>
                        <a href="#" class="contact-link">Start Chat →</a>
                    </div>
                    <div class="contact-option">
                        <div class="contact-icon">📧</div>
                        <h3 class="contact-title">Email Support</h3>
                        <p class="contact-description">Send us a detailed message and we'll respond within 24 hours.</p>
                        <a href="mailto:support@mtravels.com" class="contact-link">Send Email →</a>
                    </div>
                    <div class="contact-option">
                        <div class="contact-icon">📞</div>
                        <h3 class="contact-title">Phone Support</h3>
                        <p class="contact-description">Speak directly with our experts for urgent issues.</p>
                        <a href="tel:+93780310431" class="contact-link">Call Now →</a>
                    </div>
                    <div class="contact-option">
                        <div class="contact-icon">📚</div>
                        <h3 class="contact-title">Knowledge Base</h3>
                        <p class="contact-description">Browse our comprehensive collection of guides and tutorials.</p>
                        <a href="#" class="contact-link">Browse Articles →</a>
                    </div>
                </div>
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

        // FAQ functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                const faqItem = this.parentElement;
                const isActive = faqItem.classList.contains('active');

                // Close all FAQ items
                document.querySelectorAll('.faq-item').forEach(item => {
                    item.classList.remove('active');
                });

                // Open clicked item if it wasn't active
                if (!isActive) {
                    faqItem.classList.add('active');
                }
            });
        });

        // Search functionality
        document.querySelector('.search-btn').addEventListener('click', function() {
            const searchTerm = document.querySelector('.search-input').value.trim();
            if (searchTerm) {
                alert('Search functionality coming soon! You searched for: ' + searchTerm);
            } else {
                alert('Please enter a search term.');
            }
        });

        document.querySelector('.search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.querySelector('.search-btn').click();
            }
        });

        // Category click functionality
        document.querySelectorAll('.category-card').forEach(card => {
            card.addEventListener('click', function() {
                const category = this.querySelector('.category-title').textContent;
                alert('Category page for "' + category + '" coming soon!');
            });
        });
    </script>
</body>
</html>