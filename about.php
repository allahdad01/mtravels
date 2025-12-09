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
    <title>About Us - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Learn about MTravels - the leading travel agency management platform revolutionizing how travel businesses operate worldwide.">
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

        /* About Content */
        .about-content {
            padding: 6rem 0;
            background: var(--white);
        }

        .about-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            margin-bottom: 6rem;
        }

        .about-text h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .about-text p {
            font-size: 1.1rem;
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .about-image {
            position: relative;
        }

        .about-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        /* Stats Section */
        .stats {
            padding: 6rem 0;
            background: var(--gray-50);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }

        .stat-item {
            text-align: center;
            background: var(--white);
            border-radius: 20px;
            padding: 3rem 2rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .stat-label {
            font-size: 1.2rem;
            font-weight: 600;
            opacity: 0.9;
        }

        /* Team Section */
        .team {
            padding: 6rem 0;
            background: var(--white);
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .team-member {
            background: var(--white);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
        }

        .team-member:hover {
            transform: translateY(-5px);
        }

        .member-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .member-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .member-role {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .member-bio {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta {
            padding: 6rem 0;
            background: var(--gray-50);
            text-align: center;
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
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

            .about-grid {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .about-text h2 {
                font-size: 2rem;
            }

            .cta h2 {
                font-size: 2rem;
            }

            .cta-buttons {
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
            <h1>About <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></h1>
            <p>Revolutionizing travel agency management with innovative technology and comprehensive solutions that empower businesses worldwide.</p>
        </div>
    </section>

    <!-- About Content -->
    <section class="about-content">
        <div class="container">
            <div class="about-grid">
                <div class="about-text">
                    <h2>Our Mission</h2>
                    <p>At <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?>, we're dedicated to transforming how travel agencies operate. Our comprehensive SaaS platform combines cutting-edge technology with deep industry expertise to deliver solutions that streamline operations, boost efficiency, and drive growth.</p>
                    <p>We believe that every travel agency, regardless of size, deserves access to enterprise-level tools that simplify complex workflows and enhance customer experiences. Our mission is to empower travel professionals with the technology they need to succeed in an increasingly competitive market.</p>
                </div>
                <div class="about-image">
                    <img src="assets/images/widget/undraw_finance_m6vw.svg" alt="Our Mission" style="max-width: 100%; height: auto;">
                </div>
            </div>

            <div class="about-grid">
                <div class="about-image">
                    <img src="assets/images/widget/our_vision.jpg" alt="Our Vision" style="max-width: 100%; height: auto;">
                </div>
                <div class="about-text">
                    <h2>Our Vision</h2>
                    <p>To become the global standard for travel agency management software, recognized for innovation, reliability, and exceptional customer service. We envision a world where travel agencies can focus on what they do best - creating memorable experiences - while we handle the complexities of operations and technology.</p>
                    <p>Through continuous innovation and a customer-centric approach, we aim to set new industry benchmarks and help travel agencies thrive in the digital age.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_agencies', '10K+'); ?></div>
                    <div class="stat-label">Travel Agencies Served</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_bookings', '2M+'); ?></div>
                    <div class="stat-label">Bookings Processed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_revenue', '$500M+'); ?></div>
                    <div class="stat-label">Revenue Managed</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">🌍</div>
                    <div class="stat-number">50+</div>
                    <div class="stat-label">Countries Served</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="team">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 4rem;">
                <h2>Meet Our Team</h2>
                <p>Passionate experts dedicated to revolutionizing travel technology</p>
            </div>
            <div class="team-grid">
                <div class="team-member">
                    <div class="member-avatar">👨‍💼</div>
                    <h3 class="member-name">Ahmadullah</h3>
                    <div class="member-role">CEO & Founder</div>
                    <p class="member-bio">Visionary leader with 15+ years in travel technology, driving innovation and strategic growth for travel agencies worldwide.</p>
                </div>
                <div class="team-member">
                    <div class="member-avatar">👨‍💻</div>
                    <h3 class="member-name">Development Team</h3>
                    <div class="member-role">Technical Experts</div>
                    <p class="member-bio">Skilled developers and engineers building robust, scalable solutions that power thousands of travel agencies globally.</p>
                </div>
                <div class="team-member">
                    <div class="member-avatar">🎯</div>
                    <h3 class="member-name">Product Team</h3>
                    <div class="member-role">Product Managers</div>
                    <p class="member-bio">Dedicated product specialists ensuring our platform meets the evolving needs of modern travel agencies.</p>
                </div>
                <div class="team-member">
                    <div class="member-avatar">🛠️</div>
                    <h3 class="member-name">Support Team</h3>
                    <div class="member-role">Customer Success</div>
                    <p class="member-bio">Expert support professionals committed to helping travel agencies maximize their success with our platform.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Join Our Success Story?</h2>
            <p>Join thousands of travel agencies worldwide who trust <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?> to power their business operations.</p>
            <div class="cta-buttons">
                <a href="book-demo.php" class="btn btn-primary">Book a Demo</a>
                <a href="index.php#contact" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">Contact Us</a>
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
    </script>
</body>
</html>