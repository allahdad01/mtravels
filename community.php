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
    <title>Community - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Join the MTravels community. Connect with travel professionals, share knowledge, and get help from fellow users.">
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

        /* Community Content */
        .community-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Community Stats */
        .community-stats {
            text-align: center;
            margin-bottom: 6rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .stat-item {
            padding: 2rem;
            background: var(--gray-50);
            border-radius: 15px;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--gray-600);
            font-weight: 600;
        }

        /* Community Features */
        .community-features {
            margin-bottom: 6rem;
        }

        .community-features h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .feature-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .feature-description {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Discussion Topics */
        .discussion-topics {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .discussion-topics h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .topics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .topic-card {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .topic-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.1);
        }

        .topic-header {
            display: flex;
            align-items: start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .topic-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }

        .topic-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .topic-meta {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        .topic-content {
            color: var(--gray-600);
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .topic-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .topic-stat {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Community Guidelines */
        .community-guidelines {
            margin-bottom: 6rem;
        }

        .guidelines-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .guidelines-text h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 2rem;
        }

        .guidelines-text p {
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .guidelines-list {
            list-style: none;
            padding: 0;
        }

        .guidelines-list li {
            padding: 1rem 0;
            padding-left: 2rem;
            position: relative;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-100);
        }

        .guidelines-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
            font-size: 1.1rem;
        }

        .guidelines-list li:last-child {
            border-bottom: none;
        }

        /* Join Community */
        .join-community {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
            text-align: center;
        }

        .join-community h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .join-community p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .join-buttons {
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

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .features-grid,
            .topics-grid {
                grid-template-columns: 1fr;
            }

            .guidelines-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .guidelines-text h2 {
                font-size: 2rem;
            }

            .join-community h2 {
                font-size: 2rem;
            }

            .join-buttons {
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
            <h1>MTravels Community</h1>
            <p>Connect with fellow travel professionals, share knowledge, get help, and grow your business together in our vibrant community.</p>
        </div>
    </section>

    <!-- Community Content -->
    <section class="community-content">
        <div class="container">
            <!-- Community Stats -->
            <div class="community-stats">
                <h2 style="font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 1rem;">Join Our Growing Community</h2>
                <p style="font-size: 1.1rem; color: var(--gray-600); max-width: 600px; margin: 0 auto 4rem;">Connect with travel professionals worldwide, share insights, and access exclusive resources.</p>

                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Active Members</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">50K+</div>
                        <div class="stat-label">Discussions</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">25+</div>
                        <div class="stat-label">Countries</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">24/7</div>
                        <div class="stat-label">Support</div>
                    </div>
                </div>
            </div>

            <!-- Community Features -->
            <div class="community-features">
                <h2>Community Features</h2>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">💬</div>
                        <h3 class="feature-title">Discussion Forums</h3>
                        <p class="feature-description">Engage in topic-based discussions with travel professionals from around the world. Share experiences and learn from others.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🆘</div>
                        <h3 class="feature-title">Help & Support</h3>
                        <p class="feature-description">Get answers to your questions from community experts and MTravels support team members.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📚</div>
                        <h3 class="feature-title">Knowledge Base</h3>
                        <p class="feature-description">Access tutorials, best practices, and guides contributed by community members and industry experts.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🎯</div>
                        <h3 class="feature-title">Expert Groups</h3>
                        <p class="feature-description">Join specialized groups for specific topics like visa processing, hotel management, or financial operations.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🏆</div>
                        <h3 class="feature-title">Achievements</h3>
                        <p class="feature-description">Earn badges and recognition for your contributions to the community and helpful interactions.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">📅</div>
                        <h3 class="feature-title">Events & Webinars</h3>
                        <p class="feature-description">Participate in virtual events, webinars, and workshops hosted by industry experts and MTravels team.</p>
                    </div>
                </div>
            </div>

            <!-- Discussion Topics -->
            <div class="discussion-topics">
                <h2>Recent Discussions</h2>
                <div class="topics-grid">
                    <div class="topic-card">
                        <div class="topic-header">
                            <div class="topic-avatar">A</div>
                            <div class="topic-info">
                                <h3>Best practices for visa processing automation</h3>
                                <div class="topic-meta">Ahmed from Dubai • 2 hours ago</div>
                            </div>
                        </div>
                        <p class="topic-content">Looking for recommendations on automating visa application workflows. Currently handling 50+ applications daily...</p>
                        <div class="topic-stats">
                            <div class="topic-stat">💬 12 replies</div>
                            <div class="topic-stat">👍 8 likes</div>
                        </div>
                    </div>

                    <div class="topic-card">
                        <div class="topic-header">
                            <div class="topic-avatar">S</div>
                            <div class="topic-info">
                                <h3>Troubleshooting API integration issues</h3>
                                <div class="topic-meta">Sarah from London • 4 hours ago</div>
                            </div>
                        </div>
                        <p class="topic-content">Having trouble with webhook responses. Getting 400 errors intermittently. Has anyone experienced this?</p>
                        <div class="topic-stats">
                            <div class="topic-stat">💬 5 replies</div>
                            <div class="topic-stat">👍 3 likes</div>
                        </div>
                    </div>

                    <div class="topic-card">
                        <div class="topic-header">
                            <div class="topic-avatar">M</div>
                            <div class="topic-info">
                                <h3>Hotel booking trends for 2025</h3>
                                <div class="topic-meta">Maria from Barcelona • 6 hours ago</div>
                            </div>
                        </div>
                        <p class="topic-content">What trends are you seeing in hotel bookings? Are clients preferring direct bookings over OTAs?</p>
                        <div class="topic-stats">
                            <div class="topic-stat">💬 18 replies</div>
                            <div class="topic-stat">👍 15 likes</div>
                        </div>
                    </div>

                    <div class="topic-card">
                        <div class="topic-header">
                            <div class="topic-avatar">R</div>
                            <div class="topic-info">
                                <h3>Financial reporting best practices</h3>
                                <div class="topic-meta">Raj from Mumbai • 8 hours ago</div>
                            </div>
                        </div>
                        <p class="topic-content">Share your tips for generating accurate financial reports and managing currency conversions...</p>
                        <div class="topic-stats">
                            <div class="topic-stat">💬 9 replies</div>
                            <div class="topic-stat">👍 12 likes</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Community Guidelines -->
            <div class="community-guidelines">
                <div class="guidelines-content">
                    <div class="guidelines-text">
                        <h2>Community Guidelines</h2>
                        <p>Our community thrives on respectful, constructive interactions. Help us maintain a positive environment for all members.</p>
                        <ul class="guidelines-list">
                            <li>Be respectful and professional in all interactions</li>
                            <li>Share knowledge and help fellow community members</li>
                            <li>Stay on topic and use appropriate categories</li>
                            <li>Avoid spam and promotional content</li>
                            <li>Report inappropriate content or behavior</li>
                            <li>Protect sensitive business information</li>
                            <li>Use clear and descriptive titles for posts</li>
                            <li>Give credit when sharing others' work</li>
                        </ul>
                    </div>
                    <div class="guidelines-image">
                        <img src="assets/images/widget/undraw_finance_m6vw.svg" alt="Community Guidelines" style="width: 100%; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);">
                    </div>
                </div>
            </div>

            <!-- Join Community -->
            <div class="join-community">
                <h2>Ready to Join?</h2>
                <p>Become part of the MTravels community today and start connecting with travel professionals worldwide.</p>
                <div class="join-buttons">
                    <a href="login.php" class="btn btn-primary">Join Community</a>
                    <a href="help.php" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">Learn More</a>
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

        // Topic card click functionality
        document.querySelectorAll('.topic-card').forEach(card => {
            card.addEventListener('click', function() {
                const title = this.querySelector('.topic-info h3').textContent;
                alert('Discussion: "' + title + '" - Community forum coming soon!');
            });
        });
    </script>
</body>
</html>