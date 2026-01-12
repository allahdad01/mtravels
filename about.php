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
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - About Us'; ?></title>
    <meta name="description" content="Learn about MTravels - the platform built by travel professionals for travel professionals.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .about-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Hero Section */
        .about-hero {
            position: relative;
            padding: 8rem 2rem 5rem 2rem;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            overflow: hidden;
            text-align: center;
            margin-top: 120px;
            z-index: 1;
        }

        .about-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .about-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
        }

        .about-hero-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }

        .about-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .about-hero p {
            font-size: 1.2rem;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* Content Container */
        .about-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        /* Section */
        .about-section {
            margin-bottom: 5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .section-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            flex-shrink: 0;
        }

        .section-title {
            font-size: 2rem;
            color: var(--text-primary);
            font-weight: 700;
            margin: 0;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-top: 1.5rem;
        }

        /* Vision Section */
        .vision-box {
            background: var(--bg-secondary);
            padding: 3rem;
            border-radius: 16px;
            border-left: 4px solid #4099ff;
            margin-top: 2rem;
            transition: all 0.3s ease;
        }

        html.dark-mode .vision-box {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .vision-box p {
            color: var(--text-primary);
            font-size: 1.15rem;
            line-height: 1.8;
            font-weight: 500;
            margin: 0;
        }

        /* Problem List */
        .problem-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .problem-item {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
            transition: all 0.3s ease;
        }

        .problem-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.1);
        }

        .problem-item h4 {
            color: #ef4444;
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .problem-item p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Values Grid */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .value-card {
            background: linear-gradient(135deg, var(--bg-secondary), var(--bg-primary));
            padding: 2.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .value-card:hover {
            transform: translateY(-8px);
            border-color: #4099ff;
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.15);
        }

        .value-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .value-card h4 {
            color: var(--text-primary);
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            font-weight: 700;
        }

        .value-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Commitment Section */
        .commitment-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .commitment-item {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            transition: all 0.3s ease;
        }

        .commitment-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.1);
        }

        .commitment-item h4 {
            color: #10b981;
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .commitment-item p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Why Section */
        .why-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            margin-top: 2rem;
        }

        .why-text {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .why-point {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #4099ff;
            transition: all 0.3s ease;
        }

        .why-point h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .why-point p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        .why-visual {
            background: var(--bg-secondary);
            padding: 3rem 2rem;
            border-radius: 16px;
            border: 2px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .why-visual:hover {
            border-color: #4099ff;
        }

        .why-visual-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .why-visual-text {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.6;
        }

        /* Timeline */
        .timeline {
            position: relative;
            padding: 2rem 0;
            margin-top: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            width: 2px;
            height: 100%;
            background: linear-gradient(180deg, #4099ff, #2ed8b6);
        }

        .timeline-item {
            margin-bottom: 3rem;
            width: 45%;
            position: relative;
        }

        .timeline-item:nth-child(odd) {
            margin-left: 0;
            text-align: right;
            padding-right: 3rem;
        }

        .timeline-item:nth-child(even) {
            margin-left: auto;
            padding-left: 3rem;
        }

        .timeline-dot {
            position: absolute;
            width: 16px;
            height: 16px;
            background: #4099ff;
            border: 4px solid var(--bg-primary);
            border-radius: 50%;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
        }

        .timeline-content {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #4099ff;
        }

        .timeline-content h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .timeline-content p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin: 0;
        }

        /* Trust Section */
        .trust-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .trust-item {
            text-align: center;
            padding: 2rem 1.5rem;
            background: var(--bg-surface);
            border-radius: 16px;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-height: 280px;
            overflow: hidden;
        }

        html.dark-mode .trust-item {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        .trust-item:hover {
            border-color: #4099ff;
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(64, 153, 255, 0.15);
        }

        html.dark-mode .trust-item:hover {
            box-shadow: 0 12px 24px rgba(64, 153, 255, 0.2);
        }

        .trust-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4099ff15, #2ed8b615);
            border-radius: 12px;
            flex-shrink: 0;
        }

        .trust-item h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 0.8rem;
            font-size: 1rem;
            line-height: 1.3;
            word-break: break-word;
        }

        .trust-item p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.4;
            margin: 0;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* CTA Section */
        .about-cta {
            background: var(--bg-surface);
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 4rem;
            transition: all 0.3s ease;
        }

        html.dark-mode .about-cta {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .about-cta h3 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .about-cta p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #4099ff;
            border-color: #4099ff;
        }

        .btn-secondary:hover {
            background: rgba(64, 153, 255, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .about-hero {
                margin-top: 80px;
            }

            .about-hero h1 {
                font-size: 2.2rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .why-content {
                grid-template-columns: 1fr;
            }

            .timeline::before {
                left: 20px;
            }

            .timeline-item {
                width: 100%;
            }

            .timeline-item:nth-child(odd),
            .timeline-item:nth-child(even) {
                margin-left: 0;
                text-align: left;
                padding-left: 60px;
                padding-right: 0;
            }

            .timeline-dot {
                left: 20px;
            }

            .vision-box {
                padding: 2rem;
            }

            .trust-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .trust-item {
                min-height: auto;
            }
        }

        @media (max-width: 480px) {
            .trust-grid {
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

    <div class="about-wrapper">
        <!-- Hero -->
        <section class="about-hero">
            <div class="about-hero-content">
                <h1>Built by Travel Professionals</h1>
                <p>For Travel Professionals</p>
            </div>
        </section>

        <!-- Content -->
        <div class="about-container">

            <!-- Our Vision -->
            <div class="about-section">
                <div class="section-header">
                    <div class="section-icon">🎯</div>
                    <h2 class="section-title">Our Vision</h2>
                </div>
                
                <div class="vision-box">
                    <p>
                        To fully automate travel agency operations with accuracy, transparency, and control — 
                        so agencies can grow without chaos, make decisions with confidence, and focus on 
                        building relationships instead of fighting spreadsheets.
                    </p>
                </div>
            </div>

            <!-- Why We Built This -->
            <div class="about-section">
                <div class="section-header">
                    <div class="section-icon">💡</div>
                    <h2 class="section-title">Why We Built This</h2>
                </div>

                <p class="section-subtitle">
                    Travel agencies run on chaos. We watched agencies struggle with:
                </p>

                <div class="problem-list">
                    <div class="problem-item">
                        <h4>⚠️ Scattered Systems</h4>
                        <p>Tickets in one place, finances in Excel, clients in WhatsApp — no central truth.</p>
                    </div>
                    <div class="problem-item">
                        <h4>⚠️ Manual Everything</h4>
                        <p>Hours wasted on data entry, follow-ups, and manual reconciliation every day.</p>
                    </div>
                    <div class="problem-item">
                        <h4>⚠️ Hidden Profit Leaks</h4>
                        <p>Owners don't know if they're making money — no visibility into what's profitable.</p>
                    </div>
                    <div class="problem-item">
                        <h4>⚠️ Growth Pain</h4>
                        <p>Adding a branch means duplicating chaos, not scaling a system.</p>
                    </div>
                    <div class="problem-item">
                        <h4>⚠️ Human Error</h4>
                        <p>Manual processes = mistakes = lost money and angry customers.</p>
                    </div>
                    <div class="problem-item">
                        <h4>⚠️ Lost Compliance</h4>
                        <p>No audit trail, no accountability, compliance becomes a nightmare during audits.</p>
                    </div>
                </div>

                <p class="section-subtitle" style="margin-top: 3rem;">
                    We built MTravels to fix this — a system designed specifically for travel agencies, 
                    by people who understand the industry.
                </p>
            </div>

            <!-- What We Believe In -->
            <div class="about-section">
                <div class="section-header">
                    <div class="section-icon">⭐</div>
                    <h2 class="section-title">What We Believe In</h2>
                </div>

                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">✓</div>
                        <h4>Accuracy Over Assumptions</h4>
                        <p>Real data beats guessing. Every transaction is tracked, every number is accurate.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">💎</div>
                        <h4>Transparency in Finance</h4>
                        <p>You should know exactly where every rupiah, afghani, dirham, and dinar goes.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">🎛️</div>
                        <h4>Automation with Control</h4>
                        <p>Let the system automate — but you stay in control. No surprises, only efficiency.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">🔐</div>
                        <h4>Compliance & Accountability</h4>
                        <p>Your data is protected. Every action is logged. Audits become simple, not scary.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">🤝</div>
                        <h4>Built for Real Agencies</h4>
                        <p>Not generic software. Built from real pain points of real travel professionals.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">📈</div>
                        <h4>Growth Without Complexity</h4>
                        <p>Grow from 1 branch to 100+ without changing systems or learning new tools.</p>
                    </div>
                </div>
            </div>

            <!-- Our Commitment -->
            <div class="about-section">
                <div class="section-header">
                    <div class="section-icon">🙏</div>
                    <h2 class="section-title">Our Commitment to You</h2>
                </div>

                <div class="commitment-list">
                    <div class="commitment-item">
                        <h4>✓ Continuous Improvement</h4>
                        <p>We listen to feedback and evolve the platform based on real agency needs.</p>
                    </div>
                    <div class="commitment-item">
                        <h4>✓ Real-World Usability</h4>
                        <p>Not just powerful — easy to use. Your team should love this, not dread it.</p>
                    </div>
                    <div class="commitment-item">
                        <h4>✓ Scalable Architecture</h4>
                        <p>Built to grow with you. From startup to enterprise, the system doesn't break.</p>
                    </div>
                    <div class="commitment-item">
                        <h4>✓ Long-Term Partnership</h4>
                        <p>Your success is our success. We're here for the long haul, not a quick sale.</p>
                    </div>
                    <div class="commitment-item">
                        <h4>✓ 24/7 Support</h4>
                        <p>Questions at 2 AM? We're there. Your agency never stops, neither do we.</p>
                    </div>
                    <div class="commitment-item">
                        <h4>✓ Data Security</h4>
                        <p>Bank-level encryption. Your data is protected like your customers' money.</p>
                    </div>
                </div>
            </div>

            <!-- Why Choose Us -->
            <div class="about-section">
                <div class="section-header">
                    <div class="section-icon">🏆</div>
                    <h2 class="section-title">Why Choose MTravels</h2>
                </div>

                <div class="why-content">
                    <div class="why-text">
                        <div class="why-point">
                            <h4>✈️ Travel Industry Expertise</h4>
                            <p>Built by people who've worked in travel. We know your pain points because we've lived them.</p>
                        </div>
                        <div class="why-point">
                            <h4>🚀 Fast Implementation</h4>
                            <p>Start selling the same day. No weeks of setup. No IT department needed.</p>
                        </div>
                        <div class="why-point">
                            <h4>💰 Real ROI</h4>
                            <p>Agencies report 80% reduction in manual work, 40% fewer support tickets, 3x faster processing.</p>
                        </div>
                        <div class="why-point">
                            <h4>🌍 Multi-Currency, Multi-Location</h4>
                            <p>Designed for global operations. Handle AFN, USD, AED, EUR — scale to unlimited branches.</p>
                        </div>
                    </div>

                    <div class="why-visual">
                        <div class="why-visual-icon">🎯</div>
                        <div class="why-visual-text">
                            We're not just a software company.<br/>
                            We're your growth partner.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trust Section -->
            <div class="about-section">
                <h2 class="section-title" style="margin-bottom: 2rem;">Why Agencies Trust Us</h2>

                <div class="trust-grid">
                    <div class="trust-item">
                        <div class="trust-icon">🔒</div>
                        <h4>Bank-Level Security</h4>
                        <p>AES-256 encryption, audit logs, compliance-ready</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon">⚡</div>
                        <h4>99.9% Uptime</h4>
                        <p>Your system is always online, always available</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon">🤝</div>
                        <h4>24/7 Support</h4>
                        <p>Expert support whenever you need it</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon">📈</div>
                        <h4>Proven Results</h4>
                        <p>Thousands of agencies, millions in bookings</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon">📚</div>
                        <h4>Learning Built-In</h4>
                        <p>Tutorials, guides, webinars included</p>
                    </div>
                    <div class="trust-item">
                        <div class="trust-icon">🎓</div>
                        <h4>Onboarding Support</h4>
                        <p>We help you get set up, not just sell you software</p>
                    </div>
                </div>
            </div>

                <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2><?php echo getSetting($platform_settings, 'cta_title', 'Ready to Optimize Your Travel Operations?'); ?></h2>
            <p><?php echo getSetting($platform_settings, 'cta_subtitle', 'Join industry-leading travel agencies who have improved efficiency, reduced errors, and enhanced customer satisfaction with our comprehensive management platform.'); ?></p>
            <div class="cta-buttons">
                <a href="book-demo.php" class="btn btn-primary">
                    <?php echo getSetting($platform_settings, 'final_cta_primary', 'Schedule Your Demo'); ?>
                </a>
                <a href="how-it-works.php" class="btn btn-outline">
                    <?php echo getSetting($platform_settings, 'final_cta_secondary', 'See How It Works'); ?>
                </a>
            </div>
        </div>
    </section>
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
