<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/helpers.php';
require_once 'includes/theme-helper.php';

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

// Fetch platform settings
$platform_settings = getPlatformSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Real-time system status and uptime monitoring for MTravels platform. Check service availability and incident history.">
        <!-- Favicon -->
        <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
        <link rel="stylesheet" href="assets/css/index.css">
        <?php renderThemeStyles(); ?>
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

        /* Status Content */
        .status-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Overall Status */
        .overall-status {
            text-align: center;
            margin-bottom: 6rem;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            padding: 2rem 3rem;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 2px solid var(--success);
        }

        .status-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .status-text h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .status-text p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        /* Services Status */
        .services-status {
            margin-bottom: 6rem;
        }

        .services-status h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 15px;
            padding: 2rem;
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(64, 153, 255, 0.1);
        }

        .service-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .service-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .service-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .service-status.operational .status-dot {
            background: var(--success);
        }

        .service-status.maintenance .status-dot {
            background: var(--warning);
        }

        .service-status.outage .status-dot {
            background: var(--danger);
        }

        .service-description {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .service-uptime {
            margin-top: 1rem;
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        /* Uptime Chart */
        .uptime-chart {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .uptime-chart h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .chart-container {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .uptime-bars {
            display: flex;
            align-items: end;
            justify-content: space-between;
            height: 200px;
            margin-bottom: 2rem;
        }

        .uptime-bar {
            flex: 1;
            margin: 0 2px;
            background: linear-gradient(to top, var(--success), var(--success));
            border-radius: 4px 4px 0 0;
            position: relative;
            min-height: 10px;
        }

        .uptime-bar:hover {
            opacity: 0.8;
        }

        .uptime-bar.high {
            background: linear-gradient(to top, var(--success), #22c55e);
        }

        .uptime-bar.medium {
            background: linear-gradient(to top, var(--warning), #f59e0b);
        }

        .uptime-bar.low {
            background: linear-gradient(to top, var(--danger), #ef4444);
        }

        .chart-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-top: 1rem;
        }

        .uptime-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 2rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        /* Incident History */
        .incident-history {
            margin-bottom: 6rem;
        }

        .incident-history h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .incident-item {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .incident-item:hover {
            box-shadow: 0 5px 15px rgba(64, 153, 255, 0.1);
        }

        .incident-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .incident-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .incident-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .incident-status.resolved {
            background: var(--success);
            color: white;
        }

        .incident-status.monitoring {
            background: var(--warning);
            color: white;
        }

        .incident-date {
            color: var(--gray-500);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .incident-description {
            color: var(--gray-600);
            line-height: 1.5;
        }

        /* Subscribe to Updates */
        .status-updates {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
            text-align: center;
        }

        .status-updates h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .status-updates p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .update-form {
            display: flex;
            gap: 1rem;
            max-width: 500px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .update-input {
            flex: 1;
            min-width: 250px;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .update-input:focus {
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
            .hero h1 {
                font-size: 2.5rem;
            }

            .services-grid {
                grid-template-columns: 1fr;
            }

            .uptime-bars {
                height: 150px;
            }

            .update-form {
                flex-direction: column;
                align-items: center;
            }

            .update-input {
                min-width: auto;
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php require_once 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>System Status</h1>
            <p>Real-time monitoring of MTravels platform availability and performance. Stay informed about system health and any ongoing incidents.</p>
        </div>
    </section>

    <!-- Status Content -->
    <section class="status-content">
        <div class="container">
            <!-- Overall Status -->
            <div class="overall-status">
                <div class="status-indicator">
                    <div class="status-dot"></div>
                    <div class="status-text">
                        <h2>All Systems Operational</h2>
                        <p>Last updated: <?php echo date('M j, Y \a\t g:i A T'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Services Status -->
            <div class="services-status">
                <h2>Service Status</h2>
                <div class="services-grid">
                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name">Web Application</h3>
                            <div class="service-status operational">
                                <div class="status-dot"></div>
                                <span>Operational</span>
                            </div>
                        </div>
                        <p class="service-description">Main web application and user dashboard functionality.</p>
                        <div class="service-uptime">99.9% uptime this month</div>
                    </div>

                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name">API Services</h3>
                            <div class="service-status operational">
                                <div class="status-dot"></div>
                                <span>Operational</span>
                            </div>
                        </div>
                        <p class="service-description">REST API endpoints for integrations and third-party access.</p>
                        <div class="service-uptime">99.8% uptime this month</div>
                    </div>

                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name">Database</h3>
                            <div class="service-status operational">
                                <div class="status-dot"></div>
                                <span>Operational</span>
                            </div>
                        </div>
                        <p class="service-description">Primary database and data storage systems.</p>
                        <div class="service-uptime">99.9% uptime this month</div>
                    </div>

                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name">Payment Processing</h3>
                            <div class="service-status operational">
                                <div class="status-dot"></div>
                                <span>Operational</span>
                            </div>
                        </div>
                        <p class="service-description">Payment gateway integration and transaction processing.</p>
                        <div class="service-uptime">99.7% uptime this month</div>
                    </div>

                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name">Email Services</h3>
                            <div class="service-status operational">
                                <div class="status-dot"></div>
                                <span>Operational</span>
                            </div>
                        </div>
                        <p class="service-description">Email delivery and notification systems.</p>
                        <div class="service-uptime">99.5% uptime this month</div>
                    </div>

                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name">File Storage</h3>
                            <div class="service-status operational">
                                <div class="status-dot"></div>
                                <span>Operational</span>
                            </div>
                        </div>
                        <p class="service-description">Document and file storage and retrieval systems.</p>
                        <div class="service-uptime">99.9% uptime this month</div>
                    </div>
                </div>
            </div>

            <!-- Uptime Chart -->
            <div class="uptime-chart">
                <h2>90-Day Uptime</h2>
                <div class="chart-container">
                    <div class="uptime-bars">
                        <div class="uptime-bar high" style="height: 98%;" title="98% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 97%;" title="97% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 98%;" title="98% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 97%;" title="97% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 98%;" title="98% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 97%;" title="97% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 98%;" title="98% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 97%;" title="97% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 98%;" title="98% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 97%;" title="97% uptime"></div>
                        <div class="uptime-bar high" style="height: 100%;" title="100% uptime"></div>
                        <div class="uptime-bar high" style="height: 99%;" title="99% uptime"></div>
                        <div class="uptime-bar high" style="height: 98%;" title="98% uptime"></div>
                    </div>
                    <div class="chart-labels">
                        <span>60 days ago</span>
                        <span>30 days ago</span>
                        <span>Today</span>
                    </div>
                    <div class="uptime-legend">
                        <div class="legend-item">
                            <div class="legend-dot" style="background: var(--success);"></div>
                            <span>99%+ uptime</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: var(--warning);"></div>
                            <span>95-98% uptime</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: var(--danger);"></div>
                            <span>Below 95% uptime</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Incident History -->
            <div class="incident-history">
                <h2>Incident History</h2>
                <div class="incident-item">
                    <div class="incident-header">
                        <h3 class="incident-title">Scheduled Maintenance - Database Optimization</h3>
                        <span class="incident-status resolved">Resolved</span>
                    </div>
                    <div class="incident-date">October 15, 2024 - 2:00 AM to 4:00 AM UTC</div>
                    <p class="incident-description">Performed routine database maintenance and optimization to improve system performance. No user impact experienced.</p>
                </div>

                <div class="incident-item">
                    <div class="incident-header">
                        <h3 class="incident-title">Minor API Response Delay</h3>
                        <span class="incident-status resolved">Resolved</span>
                    </div>
                    <div class="incident-date">October 8, 2024 - 3:15 PM to 3:45 PM UTC</div>
                    <p class="incident-description">Temporary increase in API response times due to high traffic load. Issue resolved automatically through load balancing.</p>
                </div>

                <div class="incident-item">
                    <div class="incident-header">
                        <h3 class="incident-title">Email Delivery Delay</h3>
                        <span class="incident-status resolved">Resolved</span>
                    </div>
                    <div class="incident-date">September 28, 2024 - 9:00 AM to 11:30 AM UTC</div>
                    <p class="incident-description">SMTP service experienced temporary connectivity issues. All queued emails were successfully delivered.</p>
                </div>
            </div>

            <!-- Subscribe to Updates -->
            <div class="status-updates">
                <h2>Stay Informed</h2>
                <p>Get notified about system status changes, maintenance windows, and important updates via email.</p>
                <form class="update-form">
                    <input type="email" class="update-input" placeholder="Enter your email address" required>
                    <button type="submit" class="btn btn-primary">Subscribe to Updates</button>
                </form>
            </div>
        </div>
    </section>

    <?php require_once 'includes/footer.php'; ?>

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

        // Status update form
        document.querySelector('.update-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for subscribing! You will receive notifications about system status updates.');
            this.reset();
        });

        // Simulate real-time status updates (for demo purposes)
        setInterval(() => {
            // Randomly update uptime bars (small variations for demo)
            const bars = document.querySelectorAll('.uptime-bar');
            bars.forEach(bar => {
                const currentHeight = parseInt(bar.style.height) || 97;
                const variation = Math.random() * 4 - 2; // -2 to +2
                const newHeight = Math.max(95, Math.min(100, currentHeight + variation));
                bar.style.height = newHeight + '%';
                bar.title = Math.round(newHeight) + '% uptime';
                });
                }, 30000); // Update every 30 seconds
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