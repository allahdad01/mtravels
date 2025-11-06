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
    <title>Security - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Learn about MTravels security measures, data protection, compliance standards, and how we keep your travel business data safe.">
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

        /* Security Content */
        .security-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Security Overview */
        .security-overview {
            text-align: center;
            margin-bottom: 6rem;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
            margin-top: 4rem;
        }

        .overview-item {
            padding: 2rem;
            background: var(--gray-50);
            border-radius: 20px;
            text-align: center;
        }

        .overview-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .overview-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .overview-description {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Security Features */
        .security-features {
            margin-bottom: 6rem;
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

        /* Compliance Section */
        .compliance {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .compliance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }

        .compliance-item {
            text-align: center;
            padding: 2rem;
            background: var(--white);
            border-radius: 15px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }

        .compliance-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(64, 153, 255, 0.1);
        }

        .compliance-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .compliance-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .compliance-description {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Security Measures */
        .security-measures {
            margin-bottom: 6rem;
        }

        .measures-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .measure-item {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            padding: 2rem;
            background: var(--gray-50);
            border-radius: 15px;
            border-left: 4px solid var(--primary);
        }

        .measure-icon {
            font-size: 2rem;
            color: var(--primary);
            flex-shrink: 0;
            margin-top: 0.25rem;
        }

        .measure-content h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .measure-content p {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* CTA Section */
        .security-cta {
            background: var(--gray-50);
            padding: 6rem 0;
            text-align: center;
        }

        .security-cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .security-cta p {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .security-cta-buttons {
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

            .overview-grid,
            .features-grid,
            .compliance-grid {
                grid-template-columns: 1fr;
            }

            .measures-list {
                grid-template-columns: 1fr;
            }

            .measure-item {
                flex-direction: column;
                text-align: center;
            }

            .security-cta h2 {
                font-size: 2rem;
            }

            .security-cta-buttons {
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
            <h1>Security & Trust</h1>
            <p>Your data security and privacy are our top priorities. Learn how we protect your travel business with enterprise-grade security measures.</p>
        </div>
    </section>

    <!-- Security Content -->
    <section class="security-content">
        <div class="container">
            <!-- Security Overview -->
            <div class="security-overview">
                <h2 style="font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 1rem;">Why Security Matters</h2>
                <p style="font-size: 1.1rem; color: var(--gray-600); max-width: 800px; margin: 0 auto 4rem;">In the travel industry, protecting sensitive customer data, payment information, and business operations is critical. We employ comprehensive security measures to ensure your data remains safe, compliant, and accessible only to authorized personnel.</p>

                <div class="overview-grid">
                    <div class="overview-item">
                        <div class="overview-icon">🔒</div>
                        <h3 class="overview-title">Data Protection</h3>
                        <p class="overview-description">Your customer data, payment information, and business records are protected with multiple layers of encryption and security protocols.</p>
                    </div>
                    <div class="overview-item">
                        <div class="overview-icon">🛡️</div>
                        <h3 class="overview-title">Compliance</h3>
                        <p class="overview-description">We maintain compliance with international data protection standards and industry-specific regulations.</p>
                    </div>
                    <div class="overview-item">
                        <div class="overview-icon">⚡</div>
                        <h3 class="overview-title">Continuous Monitoring</h3>
                        <p class="overview-description">24/7 security monitoring and automated threat detection to identify and respond to potential security incidents.</p>
                    </div>
                </div>
            </div>

            <!-- Security Features -->
            <div class="security-features">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Security Features</h2>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">🔐</div>
                        <h3 class="feature-title">End-to-End Encryption</h3>
                        <p class="feature-description">All data is encrypted in transit and at rest using industry-standard AES-256 encryption. Your sensitive information is protected from unauthorized access.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔑</div>
                        <h3 class="feature-title">Multi-Factor Authentication</h3>
                        <p class="feature-description">Enhanced account security with mandatory multi-factor authentication for all users, ensuring only authorized personnel can access your account.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🛡️</div>
                        <h3 class="feature-title">Advanced Firewall</h3>
                        <p class="feature-description">Enterprise-grade firewall protection with intrusion detection and prevention systems to block malicious traffic and cyber threats.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">💾</div>
                        <h3 class="feature-title">Regular Backups</h3>
                        <p class="feature-description">Automated daily backups with secure off-site storage and quick recovery options to ensure business continuity.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">👁️</div>
                        <h3 class="feature-title">Security Monitoring</h3>
                        <p class="feature-description">Real-time security monitoring with automated alerts for suspicious activities and comprehensive audit logging.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">🔄</div>
                        <h3 class="feature-title">Regular Updates</h3>
                        <p class="feature-description">Continuous security updates and patches to address emerging threats and maintain the highest security standards.</p>
                    </div>
                </div>
            </div>

            <!-- Compliance Section -->
            <div class="compliance">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Compliance & Certifications</h2>

                <div class="compliance-grid">
                    <div class="compliance-item">
                        <div class="compliance-icon">🔒</div>
                        <h3 class="compliance-title">GDPR Compliant</h3>
                        <p class="compliance-description">Full compliance with General Data Protection Regulation for EU data protection standards.</p>
                    </div>
                    <div class="compliance-item">
                        <div class="compliance-icon">💳</div>
                        <h3 class="compliance-title">PCI DSS</h3>
                        <p class="compliance-description">Payment Card Industry Data Security Standard compliance for secure payment processing.</p>
                    </div>
                    <div class="compliance-item">
                        <div class="compliance-icon">🔐</div>
                        <h3 class="compliance-title">ISO 27001</h3>
                        <p class="compliance-description">International standard for information security management systems.</p>
                    </div>
                    <div class="compliance-item">
                        <div class="compliance-icon">🌐</div>
                        <h3 class="compliance-title">SOC 2 Type II</h3>
                        <p class="compliance-description">Service Organization Control for trust and security in cloud services.</p>
                    </div>
                    <div class="compliance-item">
                        <div class="compliance-icon">🇺🇸</div>
                        <h3 class="compliance-title">HIPAA Ready</h3>
                        <p class="compliance-description">Prepared for healthcare data protection standards when required.</p>
                    </div>
                    <div class="compliance-item">
                        <div class="compliance-icon">📋</div>
                        <h3 class="compliance-title">Regular Audits</h3>
                        <p class="compliance-description">Annual third-party security audits and penetration testing.</p>
                    </div>
                </div>
            </div>

            <!-- Security Measures -->
            <div class="security-measures">
                <h2 style="text-align: center; font-size: 2.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 4rem;">Our Security Measures</h2>

                <div class="measures-list">
                    <div class="measure-item">
                        <div class="measure-icon">🔐</div>
                        <div class="measure-content">
                            <h3>Data Encryption</h3>
                            <p>All sensitive data is encrypted using AES-256 encryption both in transit (TLS 1.3) and at rest. Database fields containing personal information are additionally encrypted.</p>
                        </div>
                    </div>
                    <div class="measure-item">
                        <div class="measure-icon">🛡️</div>
                        <div class="measure-content">
                            <h3>Network Security</h3>
                            <p>Multi-layered network security including Web Application Firewalls (WAF), DDoS protection, and intrusion detection systems to prevent unauthorized access.</p>
                        </div>
                    </div>
                    <div class="measure-item">
                        <div class="measure-icon">👤</div>
                        <div class="measure-content">
                            <h3>Access Control</h3>
                            <p>Role-based access control (RBAC) ensures users only have access to the data and functions necessary for their role. All access is logged and monitored.</p>
                        </div>
                    </div>
                    <div class="measure-item">
                        <div class="measure-icon">💾</div>
                        <div class="measure-content">
                            <h3>Data Backup</h3>
                            <p>Automated daily backups with secure encryption and off-site storage. Backup integrity is verified regularly with quick recovery options available.</p>
                        </div>
                    </div>
                    <div class="measure-item">
                        <div class="measure-icon">🔍</div>
                        <div class="measure-content">
                            <h3>Security Monitoring</h3>
                            <p>24/7 security monitoring with AI-powered threat detection, automated alerts, and incident response protocols to address security events promptly.</p>
                        </div>
                    </div>
                    <div class="measure-item">
                        <div class="measure-icon">📚</div>
                        <div class="measure-content">
                            <h3>Security Training</h3>
                            <p>Regular security awareness training for all team members, along with simulated phishing exercises and security best practice education.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="security-cta">
        <div class="container">
            <h2>Trust & Transparency</h2>
            <p>Security is not just a feature—it's our commitment to protecting your business. Contact our security team to learn more about our measures.</p>
            <div class="security-cta-buttons">
                <a href="mailto:security@mtravels.com" class="btn btn-primary">Contact Security Team</a>
                <a href="help.php" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">Security FAQ</a>
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