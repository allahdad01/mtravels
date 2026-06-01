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
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - How It Works'; ?></title>
    <meta name="description" content="Start selling in minutes. No IT skills needed. See how MTravels works for your travel agency.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hiw-hero {
            position: relative;
            padding: 7rem 2rem 5rem;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: #fff;
            overflow: hidden;
            text-align: center;
        }
        .hiw-hero-bg {
            position: absolute; inset: 0;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(255,255,255,.12) 0, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,255,255,.06) 0, transparent 40%);
        }
        .hiw-hero-deco {
            position: absolute; inset: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .hiw-hero-deco i {
            position: absolute;
            color: rgba(255,255,255,.08);
        }
        .hiw-hero-deco i:nth-child(1) { top: 10%; left: 8%; font-size: 2.6rem; }
        .hiw-hero-deco i:nth-child(2) { bottom: 15%; right: 10%; font-size: 2rem; }
        .hiw-hero-deco i:nth-child(3) { top: 50%; left: 4%; font-size: 1.4rem; }
        .hiw-hero-content { position: relative; z-index: 1; max-width: 780px; margin: 0 auto; }
        .hiw-hero h1 {
            font-size: clamp(2rem, 4.5vw, 3.4rem);
            font-weight: 800;
            line-height: 1.15;
            margin-bottom: 1rem;
        }
        .hiw-hero p {
            font-size: 1.15rem;
            opacity: .9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .hiw-hero .btn { font-size: 1.05rem; padding: .9rem 2.5rem; }
        .hiw-hero-steps-hint {
            display: flex;
            justify-content: center;
            gap: .5rem;
            margin-bottom: 1.5rem;
        }
        .hiw-hero-steps-hint span {
            width: 36px; height: 4px;
            border-radius: 4px;
            background: rgba(255,255,255,.2);
        }
        .hiw-hero-steps-hint span:nth-child(1) { background: rgba(255,255,255,.8); }
        .hiw-hero-steps-hint span:nth-child(2) { background: rgba(255,255,255,.5); }
        .hiw-hero-steps-hint span:nth-child(3) { background: rgba(255,255,255,.35); }
        .hiw-hero-steps-hint span:nth-child(4) { background: rgba(255,255,255,.25); }

        .hiw-section {
            padding: 5rem 2rem;
            max-width: 1100px;
            margin: 0 auto;
        }
        .hiw-section-label {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--primary, #4099ff);
            margin-bottom: .75rem;
        }
        .hiw-section-label i { font-size: .75rem; }
        .hiw-section-title {
            font-size: clamp(1.6rem, 3vw, 2.2rem);
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.2;
            margin-bottom: .75rem;
        }
        .hiw-section-sub {
            color: var(--text-secondary);
            font-size: 1.05rem;
            max-width: 600px;
            line-height: 1.6;
        }

        /* Timeline */
        .hiw-timeline {
            position: relative;
            padding: 2rem 0;
        }
        .hiw-timeline::before {
            content: '';
            position: absolute;
            top: 0; bottom: 0; left: 50%;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary, #4099ff), var(--secondary, #2ed8b6));
            transform: translateX(-50%);
        }
        .hiw-step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 3rem;
            position: relative;
        }
        .hiw-step:last-child { margin-bottom: 0; }
        .hiw-step:nth-child(odd) { flex-direction: row; }
        .hiw-step:nth-child(even) { flex-direction: row-reverse; }
        .hiw-step-card {
            width: calc(50% - 40px);
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 2rem;
            transition: all .35s ease;
            opacity: 0;
            transform: translateY(30px);
        }
        .hiw-step.visible .hiw-step-card { opacity: 1; transform: translateY(0); }
        .hiw-step:nth-child(odd) .hiw-step-card { transition-delay: .1s; }
        .hiw-step:nth-child(even) .hiw-step-card { transition-delay: .2s; }
        .hiw-step-card:hover {
            border-color: var(--primary, #4099ff);
            box-shadow: 0 8px 30px rgba(64,153,255,.1);
        }
        .hiw-step-marker {
            position: absolute;
            left: 50%; top: 2rem;
            transform: translateX(-50%);
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--primary, #4099ff), var(--secondary, #2ed8b6));
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 800;
            z-index: 2;
            box-shadow: 0 4px 14px rgba(64,153,255,.3);
            transition: transform .3s ease;
        }
        .hiw-step-card:hover + .hiw-step-marker,
        .hiw-step-marker:hover { transform: translateX(-50%) scale(1.1); }
        .hiw-step-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, rgba(64,153,255,.1), rgba(46,216,182,.1));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--primary, #4099ff);
            margin-bottom: 1rem;
        }
        .hiw-step-card h3 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: .4rem;
        }
        .hiw-step-card .hiw-step-tag {
            display: inline-block;
            font-size: .75rem;
            font-weight: 600;
            color: var(--primary, #4099ff);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: .75rem;
        }
        .hiw-step-card p {
            color: var(--text-secondary);
            font-size: .92rem;
            line-height: 1.65;
            margin-bottom: 1rem;
        }
        .hiw-step-features {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }
        .hiw-step-features span {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .78rem;
            padding: .3rem .75rem;
            background: var(--bg-secondary);
            border-radius: 20px;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        .hiw-step-features span i {
            color: var(--primary, #4099ff);
            font-size: .65rem;
        }

        /* Stats strip */
        .hiw-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            padding: 3rem 2rem;
            max-width: 1100px;
            margin: 0 auto 2rem;
        }
        .hiw-stat {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
        }
        .hiw-stat-num {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary, #4099ff);
            line-height: 1;
            margin-bottom: .35rem;
        }
        .hiw-stat-label {
            font-size: .85rem;
            color: var(--text-secondary);
        }

        /* Capabilities */
        .hiw-caps {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.25rem;
        }
        .hiw-cap {
            padding: 1.75rem;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            transition: all .3s ease;
        }
        .hiw-cap:hover {
            border-color: var(--primary, #4099ff);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(64,153,255,.08);
        }
        .hiw-cap-icon {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, rgba(64,153,255,.1), rgba(46,216,182,.1));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--primary, #4099ff);
            margin-bottom: 1rem;
        }
        .hiw-cap h4 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: .35rem;
        }
        .hiw-cap p {
            font-size: .85rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin: 0;
        }

        /* Summary banner */
        .hiw-summary {
            max-width: 1100px;
            margin: 2rem auto 3rem;
            padding: 3.5rem 3rem;
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            border-radius: 24px;
            text-align: center;
            color: #fff;
        }
        .hiw-summary h3 {
            font-size: .9rem;
            text-transform: uppercase;
            letter-spacing: .1em;
            font-weight: 700;
            opacity: .8;
            margin-bottom: .5rem;
        }
        .hiw-summary p {
            font-size: clamp(1.2rem, 2vw, 1.6rem);
            font-weight: 700;
            line-height: 1.5;
            margin: 0;
            max-width: 700px;
            margin: 0 auto;
        }

        @media (max-width: 768px) {
            .hiw-timeline::before { left: 24px; }
            .hiw-step, .hiw-step:nth-child(even) { flex-direction: column; padding-left: 56px; }
            .hiw-step-card { width: 100%; }
            .hiw-step-marker { left: 24px; top: 0; transform: translateX(-50%); }
            .hiw-stats { grid-template-columns: repeat(2, 1fr); }
            .hiw-caps { grid-template-columns: 1fr; }
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
        ['href' => 'index.php#pricing', 'label' => 'Pricing'],
        ['href' => 'index.php#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php'; 
    ?>

    <!-- Hero -->
    <section class="hiw-hero">
        <div class="hiw-hero-bg"></div>
        <div class="hiw-hero-deco">
            <i class="fas fa-plane"></i>
            <i class="fas fa-compass"></i>
            <i class="fas fa-location-dot"></i>
        </div>
        <div class="hiw-hero-content">
            <div class="hiw-hero-steps-hint">
                <span></span><span></span><span></span><span></span><span></span>
            </div>
            <h1>From Setup to Scale<br>in 5 Simple Steps</h1>
            <p>Start selling the same day — no IT skills, no complex setup, no headaches.</p>
            <a href="book-demo.php" class="btn btn-primary">Get Started Today</a>
        </div>
    </section>

    <!-- Steps -->
    <div class="hiw-section">
        <div class="hiw-section-label"><i class="fas fa-arrow-right"></i> The Process</div>
        <h2 class="hiw-section-title">Your Journey to a Fully Automated Agency</h2>
        <p class="hiw-section-sub">Five deliberate steps. No fluff. Each one builds on the last.</p>

        <div class="hiw-timeline">
            <div class="hiw-step">
                <div class="hiw-step-card">
                    <div class="hiw-step-icon"><i class="fas fa-user-plus"></i></div>
                    <h3>Create Your Agency</h3>
                    <div class="hiw-step-tag">Step 1 — Get started in minutes</div>
                    <p>Register your travel agency, set your preferences, and configure email &amp; WhatsApp once. Define your base currencies — AFN, USD, AED, EUR — and you're live.</p>
                    <div class="hiw-step-features">
                        <span><i class="fas fa-check"></i> Agency registration</span>
                        <span><i class="fas fa-check"></i> Logo &amp; branding</span>
                        <span><i class="fas fa-check"></i> SMTP + WhatsApp setup</span>
                        <span><i class="fas fa-check"></i> Multi-currency config</span>
                    </div>
                </div>
                <div class="hiw-step-marker">1</div>
            </div>

            <div class="hiw-step">
                <div class="hiw-step-card">
                    <div class="hiw-step-icon"><i class="fas fa-sitemap"></i></div>
                    <h3>Add Branches &amp; Users</h3>
                    <div class="hiw-step-tag">Step 2 — Scale without losing control</div>
                    <p>Create unlimited branches — each works independently. Add team members with role-based access: Admin, Finance, Sales, Umrah. Branch details auto-appear on every email, invoice, and PDF.</p>
                    <div class="hiw-step-features">
                        <span><i class="fas fa-check"></i> Unlimited branches</span>
                        <span><i class="fas fa-check"></i> Role-based access</span>
                        <span><i class="fas fa-check"></i> Branch-level isolation</span>
                        <span><i class="fas fa-check"></i> Auto-branded documents</span>
                    </div>
                </div>
                <div class="hiw-step-marker">2</div>
            </div>

            <div class="hiw-step">
                <div class="hiw-step-card">
                    <div class="hiw-step-icon"><i class="fas fa-store"></i></div>
                    <h3>Start Selling</h3>
                    <div class="hiw-step-tag">Step 3 — One system for everything</div>
                    <p>Manage tickets, Umrah packages, visa applications, and hotel bookings from one dashboard. Built-in OCR auto-fills data from ticket and passport PDFs — less typing, fewer errors.</p>
                    <div class="hiw-step-features">
                        <span><i class="fas fa-check"></i> Ticket booking &amp; refunds</span>
                        <span><i class="fas fa-check"></i> Umrah &amp; family mgmt</span>
                        <span><i class="fas fa-check"></i> Visa &amp; hotel bookings</span>
                        <span><i class="fas fa-check"></i> OCR auto-fill</span>
                        <span><i class="fas fa-check"></i> Multi-currency pricing</span>
                    </div>
                </div>
                <div class="hiw-step-marker">3</div>
            </div>

            <div class="hiw-step">
                <div class="hiw-step-card">
                    <div class="hiw-step-icon"><i class="fas fa-brain"></i></div>
                    <h3>Automate Everything</h3>
                    <div class="hiw-step-tag">Step 4 — This is where the magic happens</div>
                    <p>Finance tracks multi-currency cash flow automatically. Branded emails and WhatsApp messages send on every event. Audit logs record every change. Your team stops doing manual work.</p>
                    <div class="hiw-step-features">
                        <span><i class="fas fa-check"></i> Auto cash flow tracking</span>
                        <span><i class="fas fa-check"></i> Profit calc (daily/monthly)</span>
                        <span><i class="fas fa-check"></i> Email + WhatsApp auto</span>
                        <span><i class="fas fa-check"></i> Audit logs</span>
                        <span><i class="fas fa-check"></i> To-do reminders</span>
                    </div>
                </div>
                <div class="hiw-step-marker">4</div>
            </div>

            <div class="hiw-step">
                <div class="hiw-step-card">
                    <div class="hiw-step-icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Track &amp; Grow</h3>
                    <div class="hiw-step-tag">Step 5 — Data-driven decisions</div>
                    <p>Interactive dashboards show cash flow, profit breakdown, and outstanding dues. Staff performance rankings and branch comparisons help you lead. Export any report to PDF or Excel.</p>
                    <div class="hiw-step-features">
                        <span><i class="fas fa-check"></i> Cash flow dashboards</span>
                        <span><i class="fas fa-check"></i> Profit by source</span>
                        <span><i class="fas fa-check"></i> Staff rankings</span>
                        <span><i class="fas fa-check"></i> Branch comparison</span>
                        <span><i class="fas fa-check"></i> PDF / Excel export</span>
                    </div>
                </div>
                <div class="hiw-step-marker">5</div>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="hiw-stats">
        <div class="hiw-stat"><div class="hiw-stat-num">10K+</div><div class="hiw-stat-label">Travel Agencies</div></div>
        <div class="hiw-stat"><div class="hiw-stat-num">2M+</div><div class="hiw-stat-label">Bookings Processed</div></div>
        <div class="hiw-stat"><div class="hiw-stat-num">$500M+</div><div class="hiw-stat-label">Revenue Managed</div></div>
        <div class="hiw-stat"><div class="hiw-stat-num">99.9%</div><div class="hiw-stat-label">Uptime</div></div>
    </div>

    <!-- Capabilities -->
    <div class="hiw-section">
        <div class="hiw-section-label"><i class="fas fa-cube"></i> Built to Scale</div>
        <h2 class="hiw-section-title">Capabilities That Grow With You</h2>
        <p class="hiw-section-sub">From one branch to one hundred, the platform adapts.</p>
        <div class="hiw-caps">
            <div class="hiw-cap">
                <div class="hiw-cap-icon"><i class="fas fa-building"></i></div>
                <h4>Multi-Branch</h4>
                <p>Branches operate independently under one central view. Add as many as you need.</p>
            </div>
            <div class="hiw-cap">
                <div class="hiw-cap-icon"><i class="fas fa-handshake"></i></div>
                <h4>Franchise-Ready</h4>
                <p>Built to support franchise structures, multi-owner models, and revenue sharing.</p>
            </div>
            <div class="hiw-cap">
                <div class="hiw-cap-icon"><i class="fas fa-user-circle"></i></div>
                <h4>Client Portal</h4>
                <p>Clients view bookings, invoices, and trip status 24/7 — fewer support calls.</p>
            </div>
            <div class="hiw-cap">
                <div class="hiw-cap-icon"><i class="fas fa-headset"></i></div>
                <h4>Support System</h4>
                <p>Built-in ticketing with SLA tracking, categorization, and assignment.</p>
            </div>
            <div class="hiw-cap">
                <div class="hiw-cap-icon"><i class="fas fa-graduation-cap"></i></div>
                <h4>Onboarding Included</h4>
                <p>Tutorials, video guides, and documentation — your team learns as they work.</p>
            </div>
            <div class="hiw-cap">
                <div class="hiw-cap-icon"><i class="fas fa-plug"></i></div>
                <h4>API &amp; Extensions</h4>
                <p>Connect with third-party tools. Extensible architecture for custom needs.</p>
            </div>
        </div>
    </div>

    <!-- Summary -->
    <div class="hiw-summary">
        <h3>Your Journey in One Line</h3>
        <p>Sell faster, automate operations, control finance, and scale your travel agency — all from one powerful system.</p>
    </div>

    <!-- CTA -->
    <section class="cta">
        <div class="container">
            <h2><?php echo getSetting($platform_settings, 'cta_title', 'Ready to Optimize Your Travel Operations?'); ?></h2>
            <p><?php echo getSetting($platform_settings, 'cta_subtitle', 'Join industry-leading travel agencies who have improved efficiency, reduced errors, and enhanced customer satisfaction with our comprehensive management platform.'); ?></p>
            <div class="cta-buttons">
                <a href="book-demo.php" class="btn btn-primary"><?php echo getSetting($platform_settings, 'final_cta_primary', 'Get Started Today'); ?></a>
                <a href="features.php" class="btn btn-outline"><?php echo getSetting($platform_settings, 'final_cta_secondary', 'Explore All Features'); ?></a>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Timeline scroll animation
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            document.querySelectorAll('.hiw-step').forEach(function(el) {
                observer.observe(el);
            });

            // Mobile menu
            var hamburger = document.getElementById('hamburger');
            var navMenu = document.querySelector('.nav-menu');
            if (hamburger && navMenu) {
                hamburger.addEventListener('click', function() { navMenu.classList.toggle('open'); });
                document.addEventListener('click', function(e) {
                    if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) navMenu.classList.remove('open');
                });
                navMenu.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A') navMenu.classList.remove('open');
                });
            }
        });
    </script>
    <?php renderThemeScript(); ?>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
