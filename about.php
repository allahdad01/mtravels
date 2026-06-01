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
        .about-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .about-hero {
            position: relative;
            padding: 8rem 2rem 5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
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
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .about-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.04);
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
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .about-hero p {
            font-size: 1.3rem;
            opacity: .9;
            font-weight: 500;
        }

        .about-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 5rem 2rem;
        }

        .about-section {
            margin-bottom: 6rem;
        }

        .about-header {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 2.5rem;
        }

        .about-header-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, rgba(64,153,255,.08), rgba(46,216,182,.08));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            flex-shrink: 0;
            transition: all .4s ease;
            position: relative;
        }

        .about-header-icon svg {
            width: 28px;
            height: 28px;
        }

        .about-header h2 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            line-height: 1.2;
        }

        .about-subtitle {
            font-size: 1.15rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-top: 1.5rem;
        }

        /* Card base */
        .about-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: all .4s cubic-bezier(.175,.885,.32,1.275);
            position: relative;
            overflow: hidden;
        }

        html.dark-mode .about-card {
            background: var(--bg-surface);
            border-color: var(--gray-700);
        }

        .about-card::before {
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

        .about-card:hover::before {
            transform: scaleX(1);
        }

        .about-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(64,153,255,.1);
            border-color: rgba(64,153,255,.2);
        }

        .about-card-icon-wrap {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, rgba(64,153,255,.08), rgba(46,216,182,.08));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all .4s ease;
            flex-shrink: 0;
        }

        .about-card-icon-wrap svg {
            width: 24px;
            height: 24px;
        }

        .about-card:hover .about-card-icon-wrap {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 10px 25px rgba(64,153,255,.2);
        }

        /* Vision */
        .vision-card {
            padding: 2.5rem 3rem;
            margin-top: 2rem;
        }

        .vision-card p {
            color: var(--text-primary);
            font-size: 1.15rem;
            line-height: 1.8;
            font-weight: 500;
            margin: 0;
        }

        /* Problem */
        .problem-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .problem-card {
            padding: 1.8rem;
            border-radius: 20px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: all .4s cubic-bezier(.175,.885,.32,1.275);
            position: relative;
            overflow: hidden;
        }

        html.dark-mode .problem-card {
            background: var(--bg-surface);
            border-color: var(--gray-700);
        }

        .problem-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--danger);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .5s ease;
        }

        .problem-card:hover::before {
            transform: scaleX(1);
        }

        .problem-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(239,68,68,.1);
            border-color: rgba(239,68,68,.2);
        }

        .problem-header {
            display: flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: .8rem;
        }

        .problem-header svg {
            width: 20px;
            height: 20px;
            color: var(--danger);
            flex-shrink: 0;
        }

        .problem-header h4 {
            color: var(--danger);
            font-size: .95rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin: 0;
        }

        .problem-card p {
            color: var(--text-secondary);
            font-size: .93rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Values */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .value-card {
            padding: 2.5rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .value-card h4 {
            color: var(--text-primary);
            font-size: 1.1rem;
            margin: 1.2rem 0 .7rem;
            font-weight: 700;
        }

        .value-card p {
            color: var(--text-secondary);
            font-size: .93rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Commitment */
        .commitment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .commitment-card {
            padding: 1.8rem;
            border-radius: 20px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: all .4s cubic-bezier(.175,.885,.32,1.275);
            position: relative;
            overflow: hidden;
        }

        html.dark-mode .commitment-card {
            background: var(--bg-surface);
            border-color: var(--gray-700);
        }

        .commitment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--success);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .5s ease;
        }

        .commitment-card:hover::before {
            transform: scaleX(1);
        }

        .commitment-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(16,185,129,.1);
            border-color: rgba(16,185,129,.2);
        }

        .commitment-header {
            display: flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: .7rem;
        }

        .commitment-header svg {
            width: 20px;
            height: 20px;
            color: var(--success);
            flex-shrink: 0;
        }

        .commitment-header h4 {
            color: var(--success);
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
        }

        .commitment-card p {
            color: var(--text-secondary);
            font-size: .88rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Why */
        .why-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
            margin-top: 2rem;
        }

        .why-list {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }

        .why-card {
            padding: 1.5rem;
        }

        .why-card .about-card-icon-wrap {
            width: 44px;
            height: 44px;
        }

        .why-card .about-card-icon-wrap svg {
            width: 20px;
            height: 20px;
        }

        .why-card h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin: .8rem 0 .4rem;
            font-size: 1rem;
        }

        .why-card p {
            color: var(--text-secondary);
            font-size: .93rem;
            line-height: 1.6;
            margin: 0;
        }

        .why-visual-card {
            padding: 3rem 2rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 320px;
        }

        .why-visual-card .about-card-icon-wrap {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            margin-bottom: 1.5rem;
        }

        .why-visual-card .about-card-icon-wrap svg {
            width: 32px;
            height: 32px;
        }

        .why-visual-card .about-card-icon-wrap,
        .why-visual-card:hover .about-card-icon-wrap {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            box-shadow: 0 15px 35px rgba(64,153,255,.3);
            transform: none;
        }

        .why-visual-card p {
            color: var(--text-primary);
            font-size: 1.1rem;
            font-weight: 600;
            line-height: 1.6;
            margin: 0;
        }

        /* Trust */
        .trust-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .trust-card {
            padding: 2rem 1.5rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 240px;
        }

        .trust-card h4 {
            color: var(--text-primary);
            font-weight: 700;
            margin: 1rem 0 .6rem;
            font-size: 1rem;
            line-height: 1.3;
        }

        .trust-card p {
            color: var(--text-secondary);
            font-size: .85rem;
            line-height: 1.5;
            margin: 0;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .about-hero {
                margin-top: 80px;
                padding: 6rem 1.5rem 4rem;
            }

            .about-hero h1 {
                font-size: 2.2rem;
            }

            .about-hero p {
                font-size: 1.1rem;
            }

            .about-header h2 {
                font-size: 1.5rem;
            }

            .about-header-icon {
                width: 52px;
                height: 52px;
            }

            .about-header-icon svg {
                width: 22px;
                height: 22px;
            }

            .about-container {
                padding: 3rem 1.2rem;
            }

            .about-section {
                margin-bottom: 4rem;
            }

            .vision-card {
                padding: 1.8rem;
            }

            .why-grid {
                grid-template-columns: 1fr;
            }

            .trust-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .trust-card {
                min-height: auto;
                padding: 1.5rem 1.2rem;
            }
        }

        @media (max-width: 480px) {
            .trust-grid {
                grid-template-columns: 1fr;
            }

            .values-grid {
                grid-template-columns: 1fr;
            }

            .commitment-grid {
                grid-template-columns: 1fr;
            }

            .problem-grid {
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
        <section class="about-hero">
            <div class="about-hero-content">
                <h1>Built by Travel Professionals</h1>
                <p>For Travel Professionals</p>
            </div>
        </section>

        <div class="about-container">

            <!-- Our Vision -->
            <div class="about-section">
                <div class="about-header">
                    <div class="about-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <circle cx="12" cy="12" r="6"/>
                            <circle cx="12" cy="12" r="2"/>
                        </svg>
                    </div>
                    <h2>Our Vision</h2>
                </div>

                <div class="about-card vision-card">
                    <p>
                        To fully automate travel agency operations with accuracy, transparency, and control — 
                        so agencies can grow without chaos, make decisions with confidence, and focus on 
                        building relationships instead of fighting spreadsheets.
                    </p>
                </div>
            </div>

            <!-- Why We Built This -->
            <div class="about-section">
                <div class="about-header">
                    <div class="about-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 18h6"/>
                            <path d="M10 22h4"/>
                            <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"/>
                        </svg>
                    </div>
                    <h2>Why We Built This</h2>
                </div>

                <p class="about-subtitle">
                    Travel agencies run on chaos. We watched agencies struggle with:
                </p>

                <div class="problem-grid">
                    <div class="problem-card">
                        <div class="problem-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <h4>Scattered Systems</h4>
                        </div>
                        <p>Tickets in one place, finances in Excel, clients in WhatsApp — no central truth.</p>
                    </div>
                    <div class="problem-card">
                        <div class="problem-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <h4>Manual Everything</h4>
                        </div>
                        <p>Hours wasted on data entry, follow-ups, and manual reconciliation every day.</p>
                    </div>
                    <div class="problem-card">
                        <div class="problem-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <h4>Hidden Profit Leaks</h4>
                        </div>
                        <p>Owners don't know if they're making money — no visibility into what's profitable.</p>
                    </div>
                    <div class="problem-card">
                        <div class="problem-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <h4>Growth Pain</h4>
                        </div>
                        <p>Adding a branch means duplicating chaos, not scaling a system.</p>
                    </div>
                    <div class="problem-card">
                        <div class="problem-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <h4>Human Error</h4>
                        </div>
                        <p>Manual processes = mistakes = lost money and angry customers.</p>
                    </div>
                    <div class="problem-card">
                        <div class="problem-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                <line x1="12" y1="9" x2="12" y2="13"/>
                                <line x1="12" y1="17" x2="12.01" y2="17"/>
                            </svg>
                            <h4>Lost Compliance</h4>
                        </div>
                        <p>No audit trail, no accountability, compliance becomes a nightmare during audits.</p>
                    </div>
                </div>

                <p class="about-subtitle" style="margin-top: 3rem;">
                    We built MTravels to fix this — a system designed specifically for travel agencies, 
                    by people who understand the industry.
                </p>
            </div>

            <!-- What We Believe In -->
            <div class="about-section">
                <div class="about-header">
                    <div class="about-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </div>
                    <h2>What We Believe In</h2>
                </div>

                <div class="values-grid">
                    <div class="about-card value-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                <polyline points="22 4 12 14.01 9 11.01"/>
                            </svg>
                        </div>
                        <h4>Accuracy Over Assumptions</h4>
                        <p>Real data beats guessing. Every transaction is tracked, every number is accurate.</p>
                    </div>
                    <div class="about-card value-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 9l10-7 10 7-10 14L2 9z"/>
                            </svg>
                        </div>
                        <h4>Transparency in Finance</h4>
                        <p>You should know exactly where every rupiah, afghani, dirham, and dinar goes.</p>
                    </div>
                    <div class="about-card value-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="4" y1="21" x2="4" y2="14"/>
                                <line x1="4" y1="10" x2="4" y2="3"/>
                                <line x1="12" y1="21" x2="12" y2="12"/>
                                <line x1="12" y1="8" x2="12" y2="3"/>
                                <line x1="20" y1="21" x2="20" y2="16"/>
                                <line x1="20" y1="12" x2="20" y2="3"/>
                                <line x1="1" y1="14" x2="7" y2="14"/>
                                <line x1="9" y1="8" x2="15" y2="8"/>
                                <line x1="17" y1="16" x2="23" y2="16"/>
                            </svg>
                        </div>
                        <h4>Automation with Control</h4>
                        <p>Let the system automate — but you stay in control. No surprises, only efficiency.</p>
                    </div>
                    <div class="about-card value-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <h4>Compliance & Accountability</h4>
                        <p>Your data is protected. Every action is logged. Audits become simple, not scary.</p>
                    </div>
                    <div class="about-card value-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 17a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1h.5a2 2 0 0 0 2-2"/>
                                <path d="M16 17a1 1 0 0 0 1-1V9a1 1 0 0 0-1-1h-.5a2 2 0 0 1-2-2"/>
                                <path d="M2 12a1 1 0 0 1 1-1h2a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2H3"/>
                                <path d="M22 12a1 1 0 0 0-1-1h-2a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2h2"/>
                                <path d="M4 12V8a4 4 0 0 1 4-4h2"/>
                                <path d="M20 12V8a4 4 0 0 0-4-4h-2"/>
                            </svg>
                        </div>
                        <h4>Built for Real Agencies</h4>
                        <p>Not generic software. Built from real pain points of real travel professionals.</p>
                    </div>
                    <div class="about-card value-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
                                <polyline points="17 6 23 6 23 12"/>
                            </svg>
                        </div>
                        <h4>Growth Without Complexity</h4>
                        <p>Grow from 1 branch to 100+ without changing systems or learning new tools.</p>
                    </div>
                </div>
            </div>

            <!-- Our Commitment -->
            <div class="about-section">
                <div class="about-header">
                    <div class="about-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                        </svg>
                    </div>
                    <h2>Our Commitment to You</h2>
                </div>

                <div class="commitment-grid">
                    <div class="commitment-card">
                        <div class="commitment-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h4>Continuous Improvement</h4>
                        </div>
                        <p>We listen to feedback and evolve the platform based on real agency needs.</p>
                    </div>
                    <div class="commitment-card">
                        <div class="commitment-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h4>Real-World Usability</h4>
                        </div>
                        <p>Not just powerful — easy to use. Your team should love this, not dread it.</p>
                    </div>
                    <div class="commitment-card">
                        <div class="commitment-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h4>Scalable Architecture</h4>
                        </div>
                        <p>Built to grow with you. From startup to enterprise, the system doesn't break.</p>
                    </div>
                    <div class="commitment-card">
                        <div class="commitment-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h4>Long-Term Partnership</h4>
                        </div>
                        <p>Your success is our success. We're here for the long haul, not a quick sale.</p>
                    </div>
                    <div class="commitment-card">
                        <div class="commitment-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h4>24/7 Support</h4>
                        </div>
                        <p>Questions at 2 AM? We're there. Your agency never stops, neither do we.</p>
                    </div>
                    <div class="commitment-card">
                        <div class="commitment-header">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <h4>Data Security</h4>
                        </div>
                        <p>Bank-level encryption. Your data is protected like your customers' money.</p>
                    </div>
                </div>
            </div>

            <!-- Why Choose Us -->
            <div class="about-section">
                <div class="about-header">
                    <div class="about-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
                            <path d="M4 22h16"/>
                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/>
                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/>
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/>
                        </svg>
                    </div>
                    <h2>Why Choose MTravels</h2>
                </div>

                <div class="why-grid">
                    <div class="why-list">
                        <div class="about-card why-card">
                            <div class="about-card-icon-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>
                                </svg>
                            </div>
                            <h4>Travel Industry Expertise</h4>
                            <p>Built by people who've worked in travel. We know your pain points because we've lived them.</p>
                        </div>
                        <div class="about-card why-card">
                            <div class="about-card-icon-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/>
                                    <path d="M12 15c-3.5-3.5-3-8 0-11 3 3 7.5 3.5 11 0-3 3.5-2.5 8 0 11-3.5-3.5-7.5-3-11 0z"/>
                                    <path d="M9 12c-3.5 3.5-3.5 7.5 0 11 3.5-3.5 7.5-3.5 11 0-3.5-3.5-3.5-7.5 0-11-3.5 3.5-7.5 3.5-11 0z"/>
                                </svg>
                            </div>
                            <h4>Fast Implementation</h4>
                            <p>Start selling the same day. No weeks of setup. No IT department needed.</p>
                        </div>
                        <div class="about-card why-card">
                            <div class="about-card-icon-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                            </div>
                            <h4>Real ROI</h4>
                            <p>Agencies report 80% reduction in manual work, 40% fewer support tickets, 3x faster processing.</p>
                        </div>
                        <div class="about-card why-card">
                            <div class="about-card-icon-wrap">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <line x1="2" y1="12" x2="22" y2="12"/>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                                </svg>
                            </div>
                            <h4>Multi-Currency, Multi-Location</h4>
                            <p>Designed for global operations. Handle AFN, USD, AED, EUR — scale to unlimited branches.</p>
                        </div>
                    </div>

                    <div class="about-card why-visual-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <circle cx="12" cy="12" r="6"/>
                                <circle cx="12" cy="12" r="2"/>
                            </svg>
                        </div>
                        <p>We're not just a software company.<br/>We're your growth partner.</p>
                    </div>
                </div>
            </div>

            <!-- Trust Section -->
            <div class="about-section">
                <div class="about-header">
                    <div class="about-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <h2>Why Agencies Trust Us</h2>
                </div>

                <div class="trust-grid">
                    <div class="about-card trust-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <h4>Bank-Level Security</h4>
                        <p>AES-256 encryption, audit logs, compliance-ready</p>
                    </div>
                    <div class="about-card trust-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                            </svg>
                        </div>
                        <h4>99.9% Uptime</h4>
                        <p>Your system is always online, always available</p>
                    </div>
                    <div class="about-card trust-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                            </svg>
                        </div>
                        <h4>24/7 Support</h4>
                        <p>Expert support whenever you need it</p>
                    </div>
                    <div class="about-card trust-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="3" y1="20" x2="21" y2="20"/>
                                <line x1="5" y1="16" x2="5" y2="20"/>
                                <line x1="10" y1="12" x2="10" y2="20"/>
                                <line x1="15" y1="8" x2="15" y2="20"/>
                                <line x1="20" y1="4" x2="20" y2="20"/>
                            </svg>
                        </div>
                        <h4>Proven Results</h4>
                        <p>Thousands of agencies, millions in bookings</p>
                    </div>
                    <div class="about-card trust-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                                <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                            </svg>
                        </div>
                        <h4>Learning Built-In</h4>
                        <p>Tutorials, guides, webinars included</p>
                    </div>
                    <div class="about-card trust-card">
                        <div class="about-card-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                                <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                            </svg>
                        </div>
                        <h4>Onboarding Support</h4>
                        <p>We help you get set up, not just sell you software</p>
                    </div>
                </div>
            </div>

        </div>

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
