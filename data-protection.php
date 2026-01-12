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
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - Data Protection & Security'; ?></title>
    <meta name="description" content="Data Protection and Security Policy for MTravels SaaS platform.">
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
            background: linear-gradient(135deg, #10b98115, #4099ff15);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            margin: 1.5rem 0;
        }

        .legal-important {
            background: linear-gradient(135deg, #ef444415, #ef444408);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
            margin: 1.5rem 0;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .feature-box {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #4099ff;
            transition: all 0.3s ease;
        }

        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.1);
        }

        .feature-box h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .feature-box p {
            margin: 0;
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

            .feature-grid {
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
                <h1>Data Protection & Security</h1>
                <p>How we protect your travel agency data</p>
            </div>
        </section>

        <div class="legal-container">
            <div class="last-updated">
                Last Updated: January 1, 2026
            </div>

            <div class="legal-toc">
                <h3>📋 Contents</h3>
                <ul>
                    <li><a href="#commitment">Our Commitment</a></li>
                    <li><a href="#access-control">Access Control</a></li>
                    <li><a href="#encryption">Encryption & Protection</a></li>
                    <li><a href="#multi-branch">Multi-Branch Isolation</a></li>
                    <li><a href="#automation-safety">Automation Safety</a></li>
                    <li><a href="#incident">Incident Response</a></li>
                    <li><a href="#compliance">Compliance Focus</a></li>
                </ul>
            </div>

            <div class="legal-section" id="commitment">
                <h2>1. Our Commitment to Data Protection</h2>
                <p>
                    Data protection is a core principle of our Platform architecture. We are designed with 
                    "security-by-design" principles, meaning security is built into every layer of the system, 
                    not added as an afterthought.
                </p>
                <p>
                    Your travel agency data is sensitive and critical to your business. We treat it with the same 
                    rigor that financial institutions apply to protecting customer money.
                </p>

                <div class="legal-highlight">
                    <strong>✓ Security Standards:</strong> We follow industry best practices including OWASP guidelines, 
                    secure coding standards, and regular security audits.
                </div>
            </div>

            <div class="legal-section" id="access-control">
                <h2>2. Access Control & Authorization</h2>
                <p>
                    Every user action in the Platform is controlled by role-based permissions. No one can access 
                    or modify data they don't have authorization to see.
                </p>

                <h3>Role-Based Permissions</h3>
                <ul>
                    <li><strong>Tenant Super Admin:</strong> Full control. Can access all data, manage users, configure settings</li>
                    <li><strong>Admin:</strong> System administration. Can manage users and branches within their scope</li>
                    <li><strong>Finance:</strong> Financial data only. Cannot modify operational booking data</li>
                    <li><strong>Sales:</strong> Booking and client data. Cannot access financial records</li>
                    <li><strong>Umrah:</strong> Umrah family and group data only</li>
                </ul>

                <h3>Branch-Level Isolation</h3>
                <ul>
                    <li>Users can only access data from branches they are assigned to</li>
                    <li>Cross-branch data access is restricted unless explicitly authorized</li>
                    <li>Branch managers cannot see another branch's financial data or client information</li>
                    <li>Tenant Super Admin can see consolidated data across all branches</li>
                </ul>

                <h3>Admin & Finance Edit Restrictions</h3>
                <ul>
                    <li>Admins cannot modify financial records (prevents internal fraud)</li>
                    <li>Finance users cannot delete operational data (maintains data integrity)</li>
                    <li>Critical actions require confirmation to prevent accidental damage</li>
                </ul>
            </div>

            <div class="legal-section" id="encryption">
                <h2>3. Encryption & Data Protection</h2>
                <p>
                    Data is protected at every stage — transmission, storage, and processing.
                </p>

                <div class="feature-grid">
                    <div class="feature-box">
                        <h4>🔒 Secure Authentication</h4>
                        <p>Industry-standard password hashing. Optional two-factor authentication (2FA) support for maximum security.</p>
                    </div>
                    <div class="feature-box">
                        <h4>🔐 Encrypted Data Storage</h4>
                        <p>Sensitive data encrypted at rest using AES-256 encryption. Encryption keys are managed securely.</p>
                    </div>
                    <div class="feature-box">
                        <h4>🚨 TLS/SSL in Transit</h4>
                        <p>All data in transit uses HTTPS/TLS 1.2 or higher. No data transmitted in plain text.</p>
                    </div>
                    <div class="feature-box">
                        <h4>🛡️ Protection Against Attacks</h4>
                        <p>Protections against SQL injection, XSS, CSRF, and other common web vulnerabilities.</p>
                    </div>
                </div>

                <h3>Password Security</h3>
                <ul>
                    <li>Passwords must meet minimum complexity requirements</li>
                    <li>Password reset tokens expire after 24 hours</li>
                    <li>Users are encouraged to enable two-factor authentication</li>
                    <li>Failed login attempts are logged and tracked</li>
                </ul>
            </div>

            <div class="legal-section" id="multi-branch">
                <h2>4. Multi-Branch Data Isolation</h2>
                <p>
                    Each branch operates in a controlled, isolated environment while sharing agency-level configuration.
                </p>
                <ul>
                    <li><strong>Separate Operational Data:</strong> Each branch's ticket, client, and booking records are isolated</li>
                    <li><strong>Shared Configuration:</strong> Agency settings (SMTP, WhatsApp, currency) are shared for consistency</li>
                    <li><strong>Central Visibility:</strong> Tenant Super Admin has visibility into all branches for consolidated reporting</li>
                    <li><strong>Data Ownership:</strong> Your agency owns all data. We cannot access or view your records without permission</li>
                </ul>

                <div class="legal-important">
                    <strong>⚠️ Important for Multi-Branch Agencies:</strong> If a branch employee should not see another 
                    branch's data, ensure they are assigned to their specific branch only. Multi-branch access must be 
                    explicitly authorized by Tenant Super Admin.
                </div>
            </div>

            <div class="legal-section" id="automation-safety">
                <h2>5. Automation Safety & Tracking</h2>
                <p>
                    Automation features (email, WhatsApp, reminders) are designed with built-in safeguards.
                </p>

                <h3>WhatsApp & Email Automation</h3>
                <ul>
                    <li>Messages use approved templates — no uncontrolled content</li>
                    <li>Recipient lists are controlled and verified</li>
                    <li>Failed messages are recorded and retried appropriately</li>
                    <li>All messages are logged with delivery status</li>
                </ul>

                <h3>Message Delivery Logs</h3>
                <ul>
                    <li>Every email and WhatsApp sent is logged with timestamp, recipient, status</li>
                    <li>Failed sends are tracked for investigation</li>
                    <li>You can audit all outbound communications</li>
                    <li>Logs are retained for compliance purposes</li>
                </ul>

                <h3>Failure Handling</h3>
                <ul>
                    <li>SMTP failures are recorded and reported</li>
                    <li>WhatsApp API failures trigger alerts</li>
                    <li>Automatic retry with exponential backoff for transient failures</li>
                </ul>
            </div>

            <div class="legal-section" id="incident">
                <h2>6. Incident Response</h2>
                <p>
                    In the event of a security incident, we follow a structured response process to minimize impact.
                </p>

                <h3>Our Incident Response Process</h3>
                <ul>
                    <li><strong>Detection:</strong> Continuous monitoring and alert systems detect potential incidents</li>
                    <li><strong>Immediate Review:</strong> Security team conducts immediate investigation</li>
                    <li><strong>Impact Assessment:</strong> We determine what data (if any) was affected</li>
                    <li><strong>Containment:</strong> Affected systems are isolated to prevent further damage</li>
                    <li><strong>Corrective Action:</strong> Technical fixes are deployed to resolve the issue</li>
                    <li><strong>User Notification:</strong> If your data was affected, you will be notified within 24-48 hours</li>
                    <li><strong>Post-Incident Review:</strong> We conduct a full review to prevent recurrence</li>
                </ul>

                <div class="legal-highlight">
                    <strong>✓ Transparency Commitment:</strong> We will be honest and transparent about any security incidents. 
                    We will not cover up or minimize incidents.
                </div>
            </div>

            <div class="legal-section" id="compliance">
                <h2>7. Compliance & Auditability</h2>
                <p>
                    Our system is built to support compliance with financial, data protection, and travel industry regulations.
                </p>

                <h3>Core Compliance Features</h3>
                <ul>
                    <li><strong>Audit Logs:</strong> Every action (login, data change, report access) is logged with user, timestamp, IP</li>
                    <li><strong>Change Tracking:</strong> What changed, who changed it, when, and from where</li>
                    <li><strong>Financial Accuracy:</strong> All transactions recorded for audit and reconciliation</li>
                    <li><strong>User Activity Reports:</strong> Track individual user activity for accountability</li>
                    <li><strong>Export Compliance:</strong> Generate audit reports for external auditors</li>
                </ul>

                <h3>Regulations We Support</h3>
                <ul>
                    <li>Financial audit requirements (balance sheets, transaction records)</li>
                    <li>Tax compliance (invoices, payment records, financial reports)</li>
                    <li>Data protection regulations (data access logs, consent tracking)</li>
                    <li>Travel industry specific requirements (booking records, client communication logs)</li>
                </ul>

                <div class="legal-highlight">
                    <strong>✓ Audit-Ready:</strong> The system is designed to make external audits simpler and faster. 
                    All audit logs are exportable in standard formats.
                </div>
            </div>

            <!-- Closing Note -->
            <div class="legal-section">
                <h2 style="border: none; padding-bottom: 0; margin-bottom: 0; font-size: 1.5rem;">Final Note</h2>
            </div>

            <div class="legal-footer-note">
                <p><strong>🔒 Trust, Control & Scalability</strong></p>
                <p>
                    Our Platform is built for trust, control, and scalability — ensuring agencies can operate confidently 
                    while protecting their data and clients.
                </p>
                <p style="font-size: 0.9rem; margin-top: 1.5rem;">
                    For security questions or to report a vulnerability, contact us at 
                    <?php echo getSetting($platform_settings, 'contact_email', 'contact@mtravels.com'); ?>
                </p>
            </div>

            <div class="legal-cta">
                <h3>Complete Legal Documentation</h3>
                <p>Review our other legal documents to fully understand your privacy rights and platform usage.</p>
                <div class="cta-buttons">
                    <a href="privacy-policy.php" class="btn-primary">Privacy Policy</a>
                    <a href="terms-conditions.php" class="btn-primary">Terms & Conditions</a>
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
