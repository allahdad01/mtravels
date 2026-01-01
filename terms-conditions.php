<?php
session_start();

require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/pricing-helper.php';
require_once 'includes/features-helper.php';
require_once 'includes/landing-data.php';
require_once 'includes/theme-helper.php';

$landingData = fetchLandingPageData($pdo);
$platform_settings = $landingData['settings'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - Terms & Conditions'; ?></title>
    <meta name="description" content="Terms & Conditions for MTravels SaaS platform.">
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

        .legal-important {
            background: linear-gradient(135deg, #ef444415, #ef444408);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
            margin: 1.5rem 0;
        }

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

        .btn-primary {
            padding: 0.9rem 2rem;
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
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
    <div class="animated-bg"></div>
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
        <section class="legal-hero">
            <div class="legal-hero-content">
                <h1>Terms & Conditions</h1>
                <p>Legal agreement for using MTravels</p>
            </div>
        </section>

        <div class="legal-container">
            <div class="last-updated">
                Last Updated: January 1, 2026
            </div>

            <div class="legal-toc">
                <h3>📋 Contents</h3>
                <ul>
                    <li><a href="#acceptance">Acceptance of Terms</a></li>
                    <li><a href="#service-description">Service Description</a></li>
                    <li><a href="#user-responsibility">User Responsibility</a></li>
                    <li><a href="#account-control">Account Control</a></li>
                    <li><a href="#payments">Payments & Subscriptions</a></li>
                    <li><a href="#data-ownership">Data Ownership</a></li>
                    <li><a href="#limitation">Limitation of Liability</a></li>
                    <li><a href="#termination">Termination</a></li>
                </ul>
            </div>

            <div class="legal-section" id="acceptance">
                <h2>1. Acceptance of Terms</h2>
                <p>
                    By accessing or using the <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?> Platform, 
                    you agree to be bound by these Terms & Conditions. If you do not agree to any part of these terms, you may not 
                    use the Platform.
                </p>
                <p>
                    These terms constitute the entire legal agreement between you and us regarding the use of the Platform and 
                    supersede all prior negotiations, representations, and agreements.
                </p>
            </div>

            <div class="legal-section" id="service-description">
                <h2>2. Service Description</h2>
                <p>
                    The Platform provides a comprehensive, cloud-based travel agency management system. Services include:
                </p>
                <ul>
                    <li>Ticketing and reservation management</li>
                    <li>Umrah and family travel management</li>
                    <li>Visa application and hotel booking management</li>
                    <li>Financial accounting and reporting</li>
                    <li>Automation of communication and workflows</li>
                    <li>Performance analytics and dashboards</li>
                    <li>Multi-branch and multi-user management</li>
                    <li>Client portal and support systems</li>
                </ul>
                <p>
                    The Platform is provided on an "as-is" basis. We continuously improve features, but do not guarantee 
                    specific performance outcomes for your business.
                </p>
            </div>

            <div class="legal-section" id="user-responsibility">
                <h2>3. User Responsibilities</h2>
                <p>By using the Platform, you agree to:</p>
                <ul>
                    <li>Provide accurate, complete, and truthful information during registration and setup</li>
                    <li>Use the Platform legally and in compliance with all applicable laws and regulations</li>
                    <li>Not use the Platform for any fraudulent, harmful, or unauthorized purposes</li>
                    <li>Protect your login credentials and notify us of any unauthorized access immediately</li>
                    <li>Ensure all users in your agency comply with these terms</li>
                    <li>Not reverse-engineer, decompile, or attempt to gain unauthorized access to the Platform</li>
                    <li>Comply with all travel industry regulations in your jurisdiction</li>
                </ul>

                <div class="legal-highlight">
                    <strong>⚠️ Important:</strong> You are responsible for all actions taken with your account. 
                    Ensure strong passwords and restrict access to your Tenant Super Admin account.
                </div>
            </div>

            <div class="legal-section" id="account-control">
                <h2>4. Account & Role Control</h2>
                <ul>
                    <li>Each agency controls its own users, branches, and data access</li>
                    <li>Tenant Super Admin has full administrative oversight and responsibility</li>
                    <li>Role-based access controls (Admin, Finance, Sales, Umrah) enforce permissions</li>
                    <li>All actions are logged via our Audit Logs for accountability</li>
                    <li>You are responsible for managing user access and removing users when appropriate</li>
                </ul>
            </div>

            <div class="legal-section" id="payments">
                <h2>5. Payments & Subscriptions</h2>
                <ul>
                    <li>Subscription fees are billed monthly or as agreed in your contract</li>
                    <li>Payments are non-refundable unless otherwise required by law</li>
                    <li>Non-payment or subscription cancellation may result in automatic service suspension</li>
                    <li>You are responsible for keeping your billing information current</li>
                    <li>We reserve the right to modify pricing with 30 days' written notice</li>
                </ul>

                <div class="legal-important">
                    <strong>Important:</strong> Refunds are not provided for used billing periods. 
                    However, you may cancel at any time, and your access continues until the end of your current billing period.
                </div>
            </div>

            <div class="legal-section" id="data-ownership">
                <h2>6. Data Ownership</h2>
                <ul>
                    <li>All business data (tickets, clients, transactions, reports) belongs to you — your agency</li>
                    <li>We do not claim ownership of your operational data</li>
                    <li>You retain the right to export or download your data at any time</li>
                    <li>We act as a data processor on your behalf for the purpose of operating the Platform</li>
                    <li>Upon account termination, your data can be exported within 30 days</li>
                </ul>
            </div>

            <div class="legal-section" id="limitation">
                <h2>7. Limitation of Liability</h2>
                <p>
                    While we strive to provide a reliable platform, the Platform is provided "as-is" without warranties 
                    of any kind (expressed or implied).
                </p>
                <p>
                    We are not responsible for:
                </p>
                <ul>
                    <li>Changes in airline policies, pricing, or availability</li>
                    <li>Disputes between you and travel suppliers or clients</li>
                    <li>External system failures, supplier APIs, or third-party integrations</li>
                    <li>Financial decisions you make based on Platform reports or analytics</li>
                    <li>Lost profits, data loss, or business interruption (unless caused by our gross negligence)</li>
                    <li>Regulatory violations or legal liability in your jurisdiction</li>
                </ul>

                <div class="legal-highlight">
                    <strong>⚠️ Platform is a Management Tool, Not Financial Advice:</strong> The Platform helps organize 
                    your operations but is not a substitute for professional accounting, legal, or financial advice. 
                    You should consult with qualified professionals for compliance and strategic decisions.
                </div>
            </div>

            <div class="legal-section" id="termination">
                <h2>8. Termination</h2>
                <ul>
                    <li>You may terminate your account at any time by contacting us</li>
                    <li>We may suspend or terminate accounts that violate these terms or engage in fraudulent activity</li>
                    <li>Upon termination, your access to the Platform is discontinued</li>
                    <li>Data can be exported for 30 days after termination; after that, it may be deleted</li>
                </ul>
            </div>

            <div class="legal-footer-note">
                <p><strong>⚖️ Legal Compliance</strong></p>
                <p>These terms are governed by the laws of the jurisdiction where MTravels is registered and incorporated.</p>
                <p style="font-size: 0.9rem; margin-top: 1rem;">
                    Questions about our terms? Contact us at <?php echo getSetting($platform_settings, 'contact_email', 'contact@mtravels.com'); ?>
                </p>
            </div>

            <div class="legal-cta">
                <h3>Complete Legal Documentation</h3>
                <p>Review our other legal documents to fully understand your rights and obligations.</p>
                <div class="cta-buttons">
                    <a href="privacy-policy.php" class="btn-primary">Privacy Policy</a>
                    <a href="data-protection.php" class="btn-primary">Data Protection Policy</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelectorAll('.floating-element');
            parallax.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });

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
