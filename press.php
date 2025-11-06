<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/conn.php';
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
    <title>Press - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Latest news, press releases, and media resources about MTravels. Stay updated with company announcements and industry insights.">
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

        /* Press Content */
        .press-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Latest News */
        .latest-news {
            margin-bottom: 6rem;
        }

        .latest-news h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .news-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(64, 153, 255, 0.1);
        }

        .news-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }

        .news-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .news-content {
            padding: 2rem;
        }

        .news-date {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .news-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .news-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .news-title a:hover {
            color: var(--primary);
        }

        .news-excerpt {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .news-category {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Press Kit */
        .press-kit {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .press-kit h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .press-kit-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .press-kit-text h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .press-kit-text p {
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .press-kit-downloads {
            display: grid;
            gap: 1rem;
        }

        .download-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: var(--white);
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .download-item:hover {
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(64, 153, 255, 0.1);
        }

        .download-icon {
            font-size: 2rem;
            color: var(--primary);
        }

        .download-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .download-info p {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        /* Media Contact */
        .media-contact {
            margin-bottom: 6rem;
        }

        .media-contact h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .contact-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .contact-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .contact-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .contact-info {
            color: var(--gray-600);
            line-height: 1.6;
        }

        .contact-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin-top: 1rem;
            transition: all 0.3s ease;
        }

        .contact-link:hover {
            color: var(--primary-dark);
        }

        /* Newsletter Signup */
        .press-newsletter {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
            text-align: center;
        }

        .press-newsletter h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .press-newsletter p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .newsletter-form {
            display: flex;
            gap: 1rem;
            max-width: 500px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .newsletter-input {
            flex: 1;
            min-width: 250px;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .newsletter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(183, 197, 240, 0.2);
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

            .news-grid {
                grid-template-columns: 1fr;
            }

            .press-kit-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }

            .newsletter-form {
                flex-direction: column;
                align-items: center;
            }

            .newsletter-input {
                min-width: auto;
                width: 100%;
                max-width: 300px;
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
            <h1>Press Center</h1>
            <p>Stay updated with the latest news, announcements, and insights from MTravels. Get access to press releases, media resources, and company information.</p>
        </div>
    </section>

    <!-- Press Content -->
    <section class="press-content">
        <div class="container">
            <!-- Latest News -->
            <div class="latest-news">
                <h2>Latest News</h2>
                <div class="news-grid">
                    <div class="news-card">
                        <div class="news-image">🚀</div>
                        <div class="news-content">
                            <div class="news-date">November 5, 2024</div>
                            <h3 class="news-title">
                                <a href="#">MTravels Launches Advanced AI-Powered Booking System</a>
                            </h3>
                            <p class="news-excerpt">Revolutionary new features help travel agencies automate booking processes and improve customer satisfaction with intelligent recommendations.</p>
                            <span class="news-category">Product Launch</span>
                        </div>
                    </div>

                    <div class="news-card">
                        <div class="news-image">🤝</div>
                        <div class="news-content">
                            <div class="news-date">November 3, 2024</div>
                            <h3 class="news-title">
                                <a href="#">MTravels Partners with Leading Airline Consortium</a>
                            </h3>
                            <p class="news-excerpt">Strategic partnership expands global reach and provides travel agencies with access to exclusive airline deals and inventory.</p>
                            <span class="news-category">Partnership</span>
                        </div>
                    </div>

                    <div class="news-card">
                        <div class="news-image">📈</div>
                        <div class="news-content">
                            <div class="news-date">November 1, 2024</div>
                            <h3 class="news-title">
                                <a href="#">MTravels Achieves Record Growth in Q3 2024</a>
                            </h3>
                            <p class="news-excerpt">Company reports 300% year-over-year growth with 10,000+ active travel agency clients worldwide.</p>
                            <span class="news-category">Company News</span>
                        </div>
                    </div>

                    <div class="news-card">
                        <div class="news-image">🏆</div>
                        <div class="news-content">
                            <div class="news-date">October 28, 2024</div>
                            <h3 class="news-title">
                                <a href="#">MTravels Wins Travel Technology Innovation Award</a>
                            </h3>
                            <p class="news-excerpt">Recognized by industry leaders for groundbreaking contributions to travel agency management technology.</p>
                            <span class="news-category">Awards</span>
                        </div>
                    </div>

                    <div class="news-card">
                        <div class="news-image">🌍</div>
                        <div class="news-content">
                            <div class="news-date">October 25, 2024</div>
                            <h3 class="news-title">
                                <a href="#">MTravels Expands to Middle East Markets</a>
                            </h3>
                            <p class="news-excerpt">New regional office in Dubai to better serve growing Middle Eastern travel market and provide localized support.</p>
                            <span class="news-category">Expansion</span>
                        </div>
                    </div>

                    <div class="news-card">
                        <div class="news-image">🔒</div>
                        <div class="news-content">
                            <div class="news-date">October 22, 2024</div>
                            <h3 class="news-title">
                                <a href="#">MTravels Achieves SOC 2 Type II Compliance</a>
                            </h3>
                            <p class="news-excerpt">Independent audit confirms highest standards of security, availability, and confidentiality for customer data protection.</p>
                            <span class="news-category">Security</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Press Kit -->
            <div class="press-kit">
                <h2>Press Kit & Resources</h2>
                <div class="press-kit-content">
                    <div class="press-kit-text">
                        <h3>Media Resources</h3>
                        <p>Download our complete press kit with high-resolution logos, product images, executive bios, and company information. All materials are available for immediate use by media professionals.</p>
                        <p>Our press kit includes:</p>
                        <ul style="color: var(--gray-600); margin-left: 1.5rem; margin-top: 1rem;">
                            <li>• Company overview and background</li>
                            <li>• Executive team biographies</li>
                            <li>• Product screenshots and descriptions</li>
                            <li>• High-resolution logo files</li>
                            <li>• Press release templates</li>
                        </ul>
                    </div>
                    <div class="press-kit-downloads">
                        <div class="download-item">
                            <div class="download-icon">📄</div>
                            <div class="download-info">
                                <h4>Press Kit (PDF)</h4>
                                <p>Complete media kit with all resources</p>
                            </div>
                        </div>
                        <div class="download-item">
                            <div class="download-icon">🖼️</div>
                            <div class="download-info">
                                <h4>Logo Assets (ZIP)</h4>
                                <p>High-resolution logos in multiple formats</p>
                            </div>
                        </div>
                        <div class="download-item">
                            <div class="download-icon">📸</div>
                            <div class="download-info">
                                <h4>Product Images (ZIP)</h4>
                                <p>Screenshots and promotional images</p>
                            </div>
                        </div>
                        <div class="download-item">
                            <div class="download-icon">📋</div>
                            <div class="download-info">
                                <h4>Fact Sheet (PDF)</h4>
                                <p>Key company statistics and information</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Media Contact -->
            <div class="media-contact">
                <h2>Media Contact</h2>
                <div class="contact-grid">
                    <div class="contact-card">
                        <div class="contact-icon">📧</div>
                        <h3 class="contact-title">Press Inquiries</h3>
                        <div class="contact-info">
                            <p>For press releases, interviews, and media requests:</p>
                            <a href="mailto:press@mtravels.com" class="contact-link">press@mtravels.com</a>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon">📞</div>
                        <h3 class="contact-title">Phone</h3>
                        <div class="contact-info">
                            <p>Speak directly with our PR team:</p>
                            <a href="tel:+93780310431" class="contact-link">+93780310431</a>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-icon">🏢</div>
                        <h3 class="contact-title">Address</h3>
                        <div class="contact-info">
                            <p>Visit our office:</p>
                            <p>Kabul, Afghanistan</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Newsletter Signup -->
            <div class="press-newsletter">
                <h2>Press Newsletter</h2>
                <p>Subscribe to our press newsletter for exclusive access to embargoed press releases, product announcements, and industry insights.</p>
                <form class="newsletter-form">
                    <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
                    <button type="submit" class="btn btn-primary">Subscribe</button>
                </form>
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

        // Download functionality
        document.querySelectorAll('.download-item').forEach(item => {
            item.addEventListener('click', function() {
                const title = this.querySelector('h4').textContent;
                alert('Download functionality for "' + title + '" coming soon! Please contact press@mtravels.com for immediate access.');
            });
        });

        // Newsletter form
        document.querySelector('.newsletter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for subscribing to our press newsletter! You will receive updates about company news and announcements.');
            this.reset();
        });
    </script>
</body>
</html>