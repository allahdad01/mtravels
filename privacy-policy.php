<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/pricing-helper.php';
require_once 'includes/features-helper.php';
require_once 'includes/landing-data.php';
require_once 'includes/theme-helper.php';

// Fetch all landing page data
$landingData = fetchLandingPageData($pdo);
$platform_settings = $landingData['settings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - Privacy Policy'; ?></title>
    <meta name="description" content="Privacy Policy for MTravels - Learn how we protect your travel agency data.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4099ff;
            --secondary: #2ed8b6;
            --accent: #25c6b4;
            --danger: #ef4444;
            --success: #10b981;
            --dark: #0f172a;
            --light: #f8fafc;
            --border: #e2e8f0;
        }

        .legal-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Hero Section */
        .legal-hero {
            position: relative;
            padding: 6rem 2rem 4rem 2rem;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            overflow: hidden;
            text-align: center;
            margin-top: 120px;
            z-index: 1;
        }

        .legal-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .legal-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
        }

        .legal-hero-content {
            position: relative;
            z-index: 1;
        }

        .legal-hero h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -1px;
        }

        .legal-hero p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        /* Content Container */
        .legal-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        .legal-toc {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 3rem;
            border-left: 4px solid #4099ff;
        }

        .legal-toc h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .legal-toc ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.8rem;
        }

        .legal-toc li {
            margin: 0;
        }

        .legal-toc a {
            color: #4099ff;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .legal-toc a:hover {
            color: #2ed8b6;
            padding-left: 0.5rem;
        }

        /* Section */
        .legal-section {
            margin-bottom: 4rem;
            scroll-margin-top: 150px;
        }

        .legal-section h2 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #4099ff;
            font-weight: 700;
        }

        .legal-section h3 {
            font-size: 1.3rem;
            color: var(--text-primary);
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
            font-weight: 700;
        }

        .legal-section p {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .legal-section ul {
            list-style: none;
            margin-bottom: 1.5rem;
            margin-left: 0;
        }

        .legal-section li {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 0.8rem;
            padding-left: 1.8rem;
            position: relative;
            font-size: 0.95rem;
        }

        .legal-section li::before {
            content: '•';
            color: #4099ff;
            font-weight: 700;
            position: absolute;
            left: 0;
            font-size: 1.2rem;
        }

        .legal-highlight {
            background: linear-gradient(135deg, #4099ff15, #2ed8b615);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #4099ff;
            margin: 1.5rem 0;
        }

        .legal-highlight strong {
            color: #4099ff;
        }

        .legal-important {
            background: linear-gradient(135deg, #ef444415, #ef444408);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
            margin: 1.5rem 0;
        }

        .legal-important strong {
            color: #ef4444;
        }

        /* Footer Note */
        .legal-footer-note {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin-top: 4rem;
        }

        .legal-footer-note p {
            color: white !important;
            margin: 0.8rem 0;
        }

        /* CTA */
        .legal-cta {
            background: var(--bg-surface);
            padding: 3rem 2rem;
            border-radius: 12px;
            text-align: center;
            margin-top: 4rem;
            transition: all 0.3s ease;
        }

        html.dark-mode .legal-cta {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .legal-cta h3 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .legal-cta p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.3);
        }

        .last-updated {
            color: var(--text-secondary);
            font-size: 0.9rem;
            font-style: italic;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .legal-hero {
                margin-top: 80px;
                padding: 4rem 2rem 3rem 2rem;
            }

            .legal-hero h1 {
                font-size: 2rem;
            }

            .legal-section h2 {
                font-size: 1.5rem;
            }

            .legal-toc ul {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php renderThemeStyles(); ?>
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

    <div class="legal-wrapper">
        <!-- Hero -->
        <section class="legal-hero">
            <div class="legal-hero-content">
                <h1>Privacy Policy</h1>
                <p>How we protect your travel agency data</p>
            </div>
        </section>

        <!-- Content -->
        <div class="legal-container">
            <div class="last-updated">
                Last Updated: January 1, 2026
            </div>

            <!-- Table of Contents -->
            <div class="legal-toc">
                <h3>📋 Contents</h3>
                <ul>
                    <li><a href="#introduction">Introduction</a></li>
                    <li><a href="#information-collected">Information We Collect</a></li>
                    <li><a href="#how-we-use">How We Use Your Data</a></li>
                    <li><a href="#data-storage">Data Storage & Security</a></li>
                    <li><a href="#third-party">Third-Party Services</a></li>
                    <li><a href="#your-rights">Your Rights</a></li>
                    <li><a href="#policy-updates">Policy Updates</a></li>
                </ul>
            </div>

            <!-- Section 1 -->
            <div class="legal-section" id="introduction">
                <h2>1. Introduction</h2>
                <p>
                    We respect your privacy and are committed to protecting the personal and business data of our users. 
                    This Privacy Policy explains how <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?> 
                    — our Travel Agency Management SaaS platform — collects, uses, stores, and protects information.
                </p>
                <p>
                    By using our Platform, you agree to the practices described in this policy. If you have any questions 
                    about our privacy practices, please contact us at <?php echo getSetting($platform_settings, 'contact_email', 'contact@mtravels.com'); ?>.
                </p>
            </div>

            <!-- Section 2 -->
            <div class="legal-section" id="information-collected">
                <h2>2. Information We Collect</h2>
                <p>We collect only what is necessary to operate and improve the Platform.</p>

                <h3>a) Account Information</h3>
                <ul>
                    <li>Agency name and company registration details</li>
                    <li>Branch details (addresses, phone numbers, locations)</li>
                    <li>User names, roles, and email addresses</li>
                    <li>Contact details and administrative contact information</li>
                    <li>Payment and billing information (processed securely)</li>
                </ul>

                <h3>b) Operational Data</h3>
                <ul>
                    <li>Ticket records (flight numbers, dates, pricing, refund status)</li>
                    <li>Umrah and family booking information</li>
                    <li>Visa applications and hotel reservations</li>
                    <li>Financial transactions (amounts, currency, payment status)</li>
                    <li>Client records (non-sensitive identifiers, contact information)</li>
                </ul>

                <div class="legal-important">
                    <strong>⚠️ Important Note on Document Processing:</strong> We do not store passport images, ticket PDFs, 
                    or visa documents permanently. When you upload documents for OCR processing, they are processed temporarily 
                    to extract data, then securely discarded. Only extracted text data is retained in your system.
                </div>

                <h3>c) System & Usage Data</h3>
                <ul>
                    <li>Login activity and authentication logs</li>
                    <li>Audit logs (who accessed what, when, and what changes were made)</li>
                    <li>Support tickets and customer communications</li>
                    <li>System performance and usage metrics</li>
                    <li>IP addresses and device information for security purposes</li>
                </ul>
            </div>

            <!-- Section 3 -->
            <div class="legal-section" id="how-we-use">
                <h2>3. How We Use Your Data</h2>
                <p>Your data is used strictly for legitimate business purposes:</p>

                <ul>
                    <li><strong>Core Platform Operations:</strong> Managing ticket bookings, Umrah families, visa applications, financial transactions</li>
                    <li><strong>Communication:</strong> Sending system emails and WhatsApp messages (only as authorized)</li>
                    <li><strong>Reporting & Analytics:</strong> Generating dashboards, financial reports, and performance metrics</li>
                    <li><strong>Security & Compliance:</strong> Ensuring platform security, preventing fraud, and maintaining audit trails</li>
                    <li><strong>Service Improvement:</strong> Analyzing usage patterns to improve user experience and features</li>
                    <li><strong>Legal Compliance:</strong> Meeting regulatory requirements and responding to legal requests</li>
                </ul>

                <div class="legal-highlight">
                    <strong>✓ What We Don't Do:</strong> We never sell or share your data with third parties for marketing purposes. 
                    We do not use your data to train AI models without explicit consent. Your data belongs to you.
                </div>
            </div>

            <!-- Section 4 -->
            <div class="legal-section" id="data-storage">
                <h2>4. Data Storage & Security</h2>
                <p>Data protection is built into our platform architecture.</p>

                <h3>Security Measures</h3>
                <ul>
                    <li>Secure data storage using industry-standard encryption</li>
                    <li>Role-based access control ensures only authorized users can view or edit data</li>
                    <li>All critical actions are logged for accountability and auditability</li>
                    <li>Automated backups performed regularly with disaster recovery capabilities</li>
                    <li>Secure authentication including two-factor authentication support</li>
                    <li>Regular security audits and penetration testing</li>
                </ul>

                <h3>Data Retention</h3>
                <ul>
                    <li>Operational data is retained for as long as your account is active</li>
                    <li>Audit logs are retained for compliance and security purposes</li>
                    <li>Upon account deletion, your data is securely purged within 30 days</li>
                    <li>Backup copies are retained for disaster recovery for up to 90 days</li>
                </ul>
            </div>

            <!-- Section 5 -->
            <div class="legal-section" id="third-party">
                <h2>5. Third-Party Services</h2>
                <p>
                    We may use trusted third-party services to enhance our Platform functionality. These providers are 
                    carefully selected and bound by strict data protection agreements.
                </p>

                <h3>Third-Party Providers</h3>
                <ul>
                    <li><strong>Email Delivery (SMTP):</strong> For sending system emails. Only email address and message content are shared.</li>
                    <li><strong>WhatsApp Business API:</strong> For sending WhatsApp messages. Phone number and message are shared only when you authorize.</li>
                    <li><strong>Video Hosting (Vimeo):</strong> For hosting tutorial videos. No data is shared with Vimeo.</li>
                    <li><strong>Payment Processors:</strong> For secure payment processing. No credit card data is stored on our servers.</li>
                </ul>

                <div class="legal-highlight">
                    <strong>Note:</strong> All third-party providers are located in secure jurisdictions and comply with international 
                    data protection standards. We have Data Processing Agreements (DPAs) in place with each provider.
                </div>
            </div>

            <!-- Section 6 -->
            <div class="legal-section" id="your-rights">
                <h2>6. Your Rights</h2>
                <p>You have important rights regarding your data:</p>

                <ul>
                    <li><strong>Right to Access:</strong> Request a copy of all personal data we hold about you</li>
                    <li><strong>Right to Correction:</strong> Request correction or updating of inaccurate data</li>
                    <li><strong>Right to Deletion:</strong> Request deletion of your data (subject to legal retention requirements)</li>
                    <li><strong>Right to Export:</strong> Download your data in standard formats</li>
                    <li><strong>Right to Restrict Processing:</strong> Limit how your data is used</li>
                    <li><strong>Right to Account Termination:</strong> Close your account at any time</li>
                </ul>

                <p>
                    To exercise any of these rights, contact us at <?php echo getSetting($platform_settings, 'contact_email', 'contact@mtravels.com'); ?> 
                    with your request. We will respond within 30 days.
                </p>
            </div>

            <!-- Section 7 -->
            <div class="legal-section" id="policy-updates">
                <h2>7. Policy Updates</h2>
                <p>
                    We may update this Privacy Policy periodically to reflect changes in our practices, technology, 
                    legal requirements, or other factors. Any material changes will be communicated to you via email or 
                    a prominent notice on our Platform.
                </p>
                <p>
                    Your continued use of the Platform following notification of changes constitutes your acceptance of 
                    the updated Privacy Policy.
                </p>
            </div>

            <!-- Footer Note -->
            <div class="legal-footer-note">
                <p><strong>🔒 Data Protection is Our Priority</strong></p>
                <p>Your trust is the foundation of our business. We are committed to protecting your data with the highest standards.</p>
                <p style="font-size: 0.9rem; margin-top: 1rem;">
                    Questions? Contact us at <?php echo getSetting($platform_settings, 'contact_email', 'contact@mtravels.com'); ?>
                </p>
            </div>

            <!-- CTA -->
            <div class="legal-cta">
                <h3>Need Legal Documentation?</h3>
                <p>Review our Terms & Conditions and Data Protection Policy for complete legal information.</p>
                <div class="cta-buttons">
                    <a href="terms-conditions.php" class="btn btn-primary">
                    <?php echo getSetting($platform_settings, 'final_cta_primary', 'Terms & Conditions'); ?>
                </a>
                <a href="data-protection.php" class="btn btn-outline">
                    <?php echo getSetting($platform_settings, 'final_cta_secondary', 'Data Protection Policy'); ?>
                </a>
                </div>
            </div>
              
        </div>
    </div>

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

        // Mobile menu
        function toggleMobileMenu() {
            const hamburger = document.getElementById('hamburger');
            const navMenu = document.querySelector('.nav-menu');
            if (hamburger && navMenu) {
                hamburger.addEventListener('click', function() {
                    navMenu.classList.toggle('open');
                });
                document.addEventListener('click', function(event) {
                    if (!hamburger.contains(event.target) && !navMenu.contains(event.target)) {
                        navMenu.classList.remove('open');
                    }
                });
                navMenu.addEventListener('click', function(event) {
                    if (event.target.tagName === 'A') {
                        navMenu.classList.remove('open');
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleMobileMenu();
        });
    </script>
    <?php renderThemeScript(); ?>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
