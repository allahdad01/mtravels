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
    <title>Security - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Learn about MTravels security measures, data protection, compliance standards, and how we keep your travel business data safe.">
        <!-- Favicon -->
        <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
        <link rel="stylesheet" href="assets/css/index.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <?php renderThemeStyles(); ?>
    <style>
        .security-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .security-hero {
            position: relative;
            padding: 8rem 2rem 5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            overflow: hidden;
            text-align: center;
            margin-top: 120px;
            z-index: 1;
        }

        .security-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .security-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
        }

        .security-hero-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }

        .security-hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .security-hero p {
            font-size: 1.3rem;
            opacity: .9;
            font-weight: 500;
            max-width: 700px;
            margin: 0 auto;
        }

        .security-section {
            padding: 5rem 2rem;
        }

        .security-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .security-section-header {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .security-section-header h2 {
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: .8rem;
        }

        .security-section-header p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.7;
        }

        /* Overview grid */
        .sec-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .sec-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: all .4s cubic-bezier(.175,.885,.32,1.275);
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        html.dark-mode .sec-card {
            background: var(--bg-surface);
            border-color: var(--gray-700);
        }

        .sec-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .5s ease;
        }

        .sec-card:hover::before {
            transform: scaleX(1);
        }

        .sec-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(64,153,255,.1);
            border-color: rgba(64,153,255,.2);
        }

        .sec-card-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, rgba(64,153,255,.08), rgba(46,216,182,.08));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            margin: 0 auto 1.2rem;
            transition: all .4s ease;
        }

        .sec-card-icon svg {
            width: 24px;
            height: 24px;
        }

        .sec-card:hover .sec-card-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 10px 25px rgba(64,153,255,.2);
        }

        .sec-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: .7rem;
        }

        .sec-card p {
            color: var(--text-secondary);
            font-size: .93rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Measures list */
        .measures-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 1.5rem;
        }

        .measure-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: all .4s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            gap: 1.2rem;
            align-items: flex-start;
        }

        html.dark-mode .measure-card {
            background: var(--bg-surface);
            border-color: var(--gray-700);
        }

        .measure-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .5s ease;
        }

        .measure-card:hover::before {
            transform: scaleX(1);
        }

        .measure-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(64,153,255,.1);
            border-color: rgba(64,153,255,.2);
        }

        .measure-card .sec-card-icon {
            width: 48px;
            height: 48px;
            margin: 0;
            flex-shrink: 0;
        }

        .measure-card .sec-card-icon svg {
            width: 20px;
            height: 20px;
        }

        .measure-card h3 {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: .4rem;
        }

        .measure-card p {
            color: var(--text-secondary);
            font-size: .9rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Compliance section */
        .compliance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .compliance-badge {
            background: var(--bg-surface);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 1.5rem 1rem;
            text-align: center;
            transition: all .3s ease;
        }

        html.dark-mode .compliance-badge {
            border-color: var(--gray-700);
        }

        .compliance-badge:hover {
            border-color: var(--primary);
            transform: translateY(-3px);
        }

        .compliance-badge h4 {
            font-size: .9rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: .3rem;
        }

        .compliance-badge p {
            font-size: .78rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.4;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .security-hero {
                margin-top: 80px;
                padding: 6rem 1.5rem 4rem;
            }

            .security-hero h1 {
                font-size: 2.2rem;
            }

            .security-hero p {
                font-size: 1.1rem;
            }

            .security-section {
                padding: 3rem 1.2rem;
            }

            .security-section-header h2 {
                font-size: 1.6rem;
            }

            .measures-list {
                grid-template-columns: 1fr;
            }

            .measure-card {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }

            .measure-card .sec-card-icon {
                margin: 0 auto;
            }

            .security-cta h2 {
                font-size: 1.6rem;
            }

            .compliance-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .compliance-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>

    <!-- Floating Elements -->
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <?php
    $nav_links = [
        ['href' => 'index.php', 'label' => 'Home'],
        ['href' => 'features.php', 'label' => 'Features'],
        ['href' => 'how-it-works.php', 'label' => 'How It Works'],
        ['href' => 'about.php', 'label' => 'About'],
        ['href' => 'index.php#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php';
    ?>

    <div class="security-wrapper">
        <section class="security-hero">
            <div class="security-hero-content">
                <h1>Security &amp; Trust</h1>
                <p>Your data security and privacy are our top priorities. Learn how we protect your travel business with enterprise-grade security measures.</p>
            </div>
        </section>

        <!-- Overview -->
        <section class="security-section">
            <div class="security-container">
                <div class="security-section-header">
                    <h2>Why Security Matters</h2>
                    <p>In the travel industry, protecting sensitive customer data, payment information, and business operations is critical. We employ comprehensive security measures to ensure your data remains safe, compliant, and accessible only to authorized personnel.</p>
                </div>

                <div class="sec-grid">
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h3>Data Protection</h3>
                        <p>Your customer data, payment information, and business records are protected with multiple layers of encryption and security protocols.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <h3>Compliance</h3>
                        <p>We maintain compliance with international data protection standards and industry-specific regulations.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <h3>Continuous Monitoring</h3>
                        <p>24/7 security monitoring and automated threat detection to identify and respond to potential security incidents.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Security Features -->
        <section class="security-section" style="padding-top: 0;">
            <div class="security-container">
                <div class="security-section-header">
                    <h2>Security Features</h2>
                </div>

                <div class="sec-grid">
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h3>End-to-End Encryption</h3>
                        <p>All data is encrypted in transit and at rest using industry-standard AES-256 encryption. Your sensitive information is protected from unauthorized access.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0-2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <h3>Multi-Factor Authentication</h3>
                        <p>Enhanced account security with mandatory multi-factor authentication for all users, ensuring only authorized personnel can access your account.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </div>
                        <h3>Advanced Firewall</h3>
                        <p>Enterprise-grade firewall protection with intrusion detection and prevention systems to block malicious traffic and cyber threats.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h3>Regular Backups</h3>
                        <p>Automated daily backups with secure off-site storage and quick recovery options to ensure business continuity.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h3>Security Monitoring</h3>
                        <p>Real-time security monitoring with automated alerts for suspicious activities and comprehensive audit logging.</p>
                    </div>
                    <div class="sec-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <h3>Regular Updates</h3>
                        <p>Continuous security updates and patches to address emerging threats and maintain the highest security standards.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Compliance -->
        <section class="security-section" style="background: var(--bg-secondary);">
            <div class="security-container">
                <div class="security-section-header">
                    <h2>Compliance &amp; Certifications</h2>
                    <p>We adhere to international security standards and undergo regular audits to ensure the highest level of data protection.</p>
                </div>

                <div class="compliance-grid">
                    <div class="compliance-badge">
                        <h4>GDPR Compliant</h4>
                        <p>EU data protection standards</p>
                    </div>
                    <div class="compliance-badge">
                        <h4>PCI DSS</h4>
                        <p>Secure payment processing</p>
                    </div>
                    <div class="compliance-badge">
                        <h4>ISO 27001</h4>
                        <p>Information security management</p>
                    </div>
                    <div class="compliance-badge">
                        <h4>SOC 2 Type II</h4>
                        <p>Cloud service trust &amp; security</p>
                    </div>
                    <div class="compliance-badge">
                        <h4>HIPAA Ready</h4>
                        <p>Healthcare data protection</p>
                    </div>
                    <div class="compliance-badge">
                        <h4>Regular Audits</h4>
                        <p>Annual third-party penetration testing</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Security Measures -->
        <section class="security-section">
            <div class="security-container">
                <div class="security-section-header">
                    <h2>Our Security Measures</h2>
                </div>

                <div class="measures-list">
                    <div class="measure-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <div>
                            <h3>Data Encryption</h3>
                            <p>All sensitive data is encrypted using AES-256 encryption both in transit (TLS 1.3) and at rest. Database fields containing personal information are additionally encrypted.</p>
                        </div>
                    </div>
                    <div class="measure-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                        </div>
                        <div>
                            <h3>Network Security</h3>
                            <p>Multi-layered network security including Web Application Firewalls (WAF), DDoS protection, and intrusion detection systems to prevent unauthorized access.</p>
                        </div>
                    </div>
                    <div class="measure-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div>
                            <h3>Access Control</h3>
                            <p>Role-based access control (RBAC) ensures users only have access to the data and functions necessary for their role. All access is logged and monitored.</p>
                        </div>
                    </div>
                    <div class="measure-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <div>
                            <h3>Data Backup</h3>
                            <p>Automated daily backups with secure encryption and off-site storage. Backup integrity is verified regularly with quick recovery options available.</p>
                        </div>
                    </div>
                    <div class="measure-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <div>
                            <h3>Security Monitoring</h3>
                            <p>24/7 security monitoring with AI-powered threat detection, automated alerts, and incident response protocols to address security events promptly.</p>
                        </div>
                    </div>
                    <div class="measure-card">
                        <div class="sec-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                        </div>
                        <div>
                            <h3>Security Training</h3>
                            <p>Regular security awareness training for all team members, along with simulated phishing exercises and security best practice education.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <?php require_once 'includes/footer.php'; ?>

    <script>
        // Parallax effect
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelectorAll('.floating-element');
            parallax.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });
    </script>
    <?php renderThemeScript(); ?>
</body>
</html>