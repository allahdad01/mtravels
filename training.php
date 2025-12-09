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
    <title>Training - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Comprehensive training programs for MTravels platform. Learn to maximize your travel agency's potential with expert-led courses and certifications.">
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

        /* Training Content */
        .training-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Training Programs */
        .training-programs {
            margin-bottom: 6rem;
        }

        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .program-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .program-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .program-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .program-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .program-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .program-features {
            text-align: left;
            margin-bottom: 2rem;
        }

        .program-features h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .program-features ul {
            list-style: none;
            padding: 0;
        }

        .program-features li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--gray-600);
        }

        .program-features li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        .program-duration {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-bottom: 1rem;
        }

        /* Certification Paths */
        .certification-paths {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .certification-paths h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .paths-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .path-card {
            background: var(--white);
            border-radius: 15px;
            padding: 2.5rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .path-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(64, 153, 255, 0.1);
        }

        .path-level {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--primary);
            color: white;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .path-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .path-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .path-requirements {
            text-align: left;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .path-requirements h4 {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        /* Learning Resources */
        .learning-resources {
            margin-bottom: 6rem;
        }

        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .resource-item {
            background: var(--gray-50);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .resource-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.1);
        }

        .resource-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .resource-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .resource-description {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Training Schedule */
        .training-schedule {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .schedule-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .schedule-text h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 2rem;
        }

        .schedule-text p {
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .upcoming-courses {
            background: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .course-item {
            padding: 1.5rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .course-details {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .course-date {
            font-size: 0.8rem;
            color: var(--primary);
            font-weight: 600;
        }

        /* CTA Section */
        .training-cta {
            background: var(--gray-50);
            padding: 6rem 0;
            text-align: center;
        }

        .training-cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .training-cta p {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .training-cta-buttons {
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

            .programs-grid,
            .paths-grid,
            .resources-grid {
                grid-template-columns: 1fr;
            }

            .schedule-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .schedule-text h2 {
                font-size: 2rem;
            }

            .training-cta h2 {
                font-size: 2rem;
            }

            .training-cta-buttons {
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
            <h1>Training & Certification</h1>
            <p>Master MTravels platform with comprehensive training programs designed for travel professionals. Get certified and unlock your agency's full potential.</p>
        </div>
    </section>

    <!-- Training Content -->
    <section class="training-content">
        <div class="container">
            <!-- Training Programs -->
            <div class="training-programs">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Training Programs</h2>

                <div class="programs-grid">
                    <div class="program-card">
                        <div class="program-icon">🚀</div>
                        <h3 class="program-title">Getting Started</h3>
                        <p class="program-description">Perfect for new users. Learn the basics of MTravels platform and get up to speed quickly.</p>
                        <div class="program-duration">Duration: 2 hours</div>
                        <div class="program-features">
                            <h4>What You'll Learn:</h4>
                            <ul>
                                <li>Platform navigation and interface</li>
                                <li>Basic booking operations</li>
                                <li>User account setup</li>
                                <li>Essential features overview</li>
                            </ul>
                        </div>
                    </div>

                    <div class="program-card">
                        <div class="program-icon">⚡</div>
                        <h3 class="program-title">Advanced Operations</h3>
                        <p class="program-description">Deep dive into advanced features and workflows for experienced travel professionals.</p>
                        <div class="program-duration">Duration: 4 hours</div>
                        <div class="program-features">
                            <h4>What You'll Learn:</h4>
                            <ul>
                                <li>Complex booking scenarios</li>
                                <li>Financial management tools</li>
                                <li>Reporting and analytics</li>
                                <li>Integration setup</li>
                            </ul>
                        </div>
                    </div>

                    <div class="program-card">
                        <div class="program-icon">👥</div>
                        <h3 class="program-title">Team Management</h3>
                        <p class="program-description">Learn to manage multiple users, set permissions, and optimize team workflows.</p>
                        <div class="program-duration">Duration: 3 hours</div>
                        <div class="program-features">
                            <h4>What You'll Learn:</h4>
                            <ul>
                                <li>User role management</li>
                                <li>Permission settings</li>
                                <li>Team collaboration tools</li>
                                <li>Workflow optimization</li>
                            </ul>
                        </div>
                    </div>

                    <div class="program-card">
                        <div class="program-icon">🔧</div>
                        <h3 class="program-title">API Integration</h3>
                        <p class="program-description">Master API integration for custom solutions and third-party connections.</p>
                        <div class="program-duration">Duration: 6 hours</div>
                        <div class="program-features">
                            <h4>What You'll Learn:</h4>
                            <ul>
                                <li>API authentication</li>
                                <li>Webhook configuration</li>
                                <li>Custom integrations</li>
                                <li>Troubleshooting techniques</li>
                            </ul>
                        </div>
                    </div>

                    <div class="program-card">
                        <div class="program-icon">📊</div>
                        <h3 class="program-title">Business Intelligence</h3>
                        <p class="program-description">Unlock the power of data with advanced reporting and business intelligence tools.</p>
                        <div class="program-duration">Duration: 5 hours</div>
                        <div class="program-features">
                            <h4>What You'll Learn:</h4>
                            <ul>
                                <li>Advanced reporting</li>
                                <li>Data visualization</li>
                                <li>Performance analytics</li>
                                <li>Business insights</li>
                            </ul>
                        </div>
                    </div>

                    <div class="program-card">
                        <div class="program-icon">🛡️</div>
                        <h3 class="program-title">Security & Compliance</h3>
                        <p class="program-description">Learn best practices for data security, compliance, and regulatory requirements.</p>
                        <div class="program-duration">Duration: 3 hours</div>
                        <div class="program-features">
                            <h4>What You'll Learn:</h4>
                            <ul>
                                <li>Data protection principles</li>
                                <li>Compliance requirements</li>
                                <li>Security best practices</li>
                                <li>Risk management</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Certification Paths -->
            <div class="certification-paths">
                <h2>Certification Paths</h2>
                <div class="paths-grid">
                    <div class="path-card">
                        <div class="path-level">Foundation</div>
                        <h3 class="path-title">MTravels Certified User</h3>
                        <p class="path-description">Demonstrate basic proficiency in using MTravels platform for daily operations.</p>
                        <div class="path-requirements">
                            <h4>Requirements:</h4>
                            <ul>
                                <li>Complete Getting Started course</li>
                                <li>Pass online assessment</li>
                                <li>Basic platform knowledge</li>
                            </ul>
                        </div>
                    </div>

                    <div class="path-card">
                        <div class="path-level">Professional</div>
                        <h3 class="path-title">MTravels Certified Professional</h3>
                        <p class="path-description">Show advanced skills in complex operations and team management.</p>
                        <div class="path-requirements">
                            <h4>Requirements:</h4>
                            <ul>
                                <li>MTravels Certified User</li>
                                <li>Advanced Operations course</li>
                                <li>Team Management course</li>
                                <li>Practical assessment</li>
                            </ul>
                        </div>
                    </div>

                    <div class="path-card">
                        <div class="path-level">Expert</div>
                        <h3 class="path-title">MTravels Certified Expert</h3>
                        <p class="path-description">Master-level certification for integration specialists and power users.</p>
                        <div class="path-requirements">
                            <h4>Requirements:</h4>
                            <ul>
                                <li>MTravels Certified Professional</li>
                                <li>API Integration course</li>
                                <li>Business Intelligence course</li>
                                <li>Project submission</li>
                            </ul>
                        </div>
                    </div>

                    <div class="path-card">
                        <div class="path-level">Master</div>
                        <h3 class="path-title">MTravels Master Trainer</h3>
                        <p class="path-description">Elite certification for training other users and implementing best practices.</p>
                        <div class="path-requirements">
                            <h4>Requirements:</h4>
                            <ul>
                                <li>MTravels Certified Expert</li>
                                <li>2+ years platform experience</li>
                                <li>Training delivery experience</li>
                                <li>Peer review process</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Learning Resources -->
            <div class="learning-resources">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Learning Resources</h2>

                <div class="resources-grid">
                    <div class="resource-item">
                        <div class="resource-icon">📚</div>
                        <h3 class="resource-title">Knowledge Base</h3>
                        <p class="resource-description">Comprehensive documentation, tutorials, and guides for all platform features.</p>
                    </div>
                    <div class="resource-item">
                        <div class="resource-icon">🎥</div>
                        <h3 class="resource-title">Video Tutorials</h3>
                        <p class="resource-description">Step-by-step video guides covering common tasks and advanced features.</p>
                    </div>
                    <div class="resource-item">
                        <div class="resource-icon">💬</div>
                        <h3 class="resource-title">Community Forum</h3>
                        <p class="resource-description">Connect with other users, ask questions, and share your knowledge.</p>
                    </div>
                    <div class="resource-item">
                        <div class="resource-icon">📋</div>
                        <h3 class="resource-title">Best Practices</h3>
                        <p class="resource-description">Industry best practices and optimization tips from experienced users.</p>
                    </div>
                    <div class="resource-item">
                        <div class="resource-icon">📰</div>
                        <h3 class="resource-title">Release Notes</h3>
                        <p class="resource-description">Stay updated with latest features, improvements, and bug fixes.</p>
                    </div>
                    <div class="resource-item">
                        <div class="resource-icon">🎯</div>
                        <h3 class="resource-title">Case Studies</h3>
                        <p class="resource-description">Real-world examples of how agencies use MTravels to grow their business.</p>
                    </div>
                </div>
            </div>

            <!-- Training Schedule -->
            <div class="training-schedule">
                <div class="container">
                    <div class="schedule-content">
                        <div class="schedule-text">
                            <h2>Upcoming Training Sessions</h2>
                            <p>Join live training sessions led by MTravels experts. Learn directly from the team that built the platform and get your questions answered in real-time.</p>
                            <p>All sessions are recorded and available on-demand for registered participants.</p>
                        </div>
                        <div class="upcoming-courses">
                            <div class="course-item">
                                <h3 class="course-title">Getting Started with MTravels</h3>
                                <div class="course-details">Live webinar • 2 hours</div>
                                <div class="course-date">November 15, 2024 • 10:00 AM UTC</div>
                            </div>
                            <div class="course-item">
                                <h3 class="course-title">Advanced Booking Workflows</h3>
                                <div class="course-details">Live training • 3 hours</div>
                                <div class="course-date">November 22, 2024 • 2:00 PM UTC</div>
                            </div>
                            <div class="course-item">
                                <h3 class="course-title">API Integration Masterclass</h3>
                                <div class="course-details">Live workshop • 4 hours</div>
                                <div class="course-date">November 28, 2024 • 9:00 AM UTC</div>
                            </div>
                            <div class="course-item">
                                <h3 class="course-title">Financial Reporting Excellence</h3>
                                <div class="course-details">Live training • 2.5 hours</div>
                                <div class="course-date">December 5, 2024 • 11:00 AM UTC</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="training-cta">
        <div class="container">
            <h2>Start Your Learning Journey</h2>
            <p>Invest in your skills and become a MTravels expert. Join thousands of travel professionals who have enhanced their careers with our training programs.</p>
            <div class="training-cta-buttons">
                <a href="book-demo.php" class="btn btn-primary">Enroll Now</a>
                <a href="help.php" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">View Resources</a>
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

        // Program card click functionality
        document.querySelectorAll('.program-card').forEach(card => {
            card.addEventListener('click', function() {
                const title = this.querySelector('.program-title').textContent;
                alert('Training Program: "' + title + '" - Registration coming soon!');
            });
        });

        // Certification path click functionality
        document.querySelectorAll('.path-card').forEach(card => {
            card.addEventListener('click', function() {
                const title = this.querySelector('.path-title').textContent;
                alert('Certification: "' + title + '" - Learn more about requirements and enrollment!');
            });
        });
    </script>
</body>
</html>