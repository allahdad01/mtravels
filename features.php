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
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - Complete Feature Breakdown'; ?></title>
    <meta name="description" content="All-in-one travel agency SaaS: ticketing, Umrah, visas, hotels, finance, automation, reporting, client portal & more.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .features-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .features-hero {
            position: relative;
            padding: 8rem 2rem 5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            overflow: hidden;
            text-align: center;
            margin-top: 120px;
            z-index: 1;
        }

        .features-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .features-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.04);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
        }

        .features-hero-content {
            position: relative;
            z-index: 1;
            max-width: 1000px;
            margin: 0 auto;
        }

        .features-hero-content h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .features-hero-subtitle {
            font-size: 1.3rem;
            opacity: .9;
            font-weight: 500;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .features-hero-tagline {
            background: rgba(255, 255, 255, 0.15);
            padding: 1.2rem 2rem;
            border-radius: 60px;
            font-size: 1rem;
            font-weight: 600;
            display: inline-block;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .features-layout {
            display: flex;
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
            align-items: flex-start;
        }

        .features-sidebar {
            width: 220px;
            flex-shrink: 0;
            position: sticky;
            top: 150px;
            max-height: calc(100vh - 180px);
            overflow-y: auto;
            padding: 1.2rem .8rem;
            background: var(--bg-surface);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: background .3s ease, border-color .3s ease;
        }

        html.dark-mode .features-sidebar {
            border-color: var(--gray-700);
        }

        .features-sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .features-sidebar::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 2px;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .55rem .75rem;
            border-radius: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: .82rem;
            font-weight: 600;
            transition: all .25s ease;
            cursor: pointer;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }

        .sidebar-link svg {
            width: 16px;
            height: 16px;
            flex-shrink: 0;
            color: var(--gray-400);
            transition: color .25s ease;
        }

        .sidebar-link:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .sidebar-link:hover svg {
            color: var(--primary);
        }

        .sidebar-link.active {
            background: linear-gradient(135deg, rgba(64,153,255,.1), rgba(46,216,182,.1));
            color: var(--primary);
            font-weight: 700;
        }

        .sidebar-link.active svg {
            color: var(--primary);
        }

        .features-content {
            flex: 1;
            min-width: 0;
        }

        .features-content .features-container {
            padding: 0;
        }

        .feature-category {
            margin-bottom: 5rem;
            scroll-margin-top: 100px;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .feat-header {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .feat-header-icon {
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
        }

        .feat-header-icon svg {
            width: 28px;
            height: 28px;
        }

        .feat-header h2 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            line-height: 1.2;
        }

        .feat-intro {
            font-size: 1.05rem;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 2.5rem;
            max-width: 800px;
        }

        .feat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .feat-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 20px;
            padding: 2rem 1.8rem;
            box-shadow: 0 4px 20px rgba(0,0,0,.04);
            transition: all .4s cubic-bezier(.175,.885,.32,1.275);
            position: relative;
            overflow: hidden;
        }

        html.dark-mode .feat-card {
            background: var(--bg-surface);
            border-color: var(--gray-700);
        }

        .feat-card::before {
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

        .feat-card:hover::before {
            transform: scaleX(1);
        }

        .feat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(64,153,255,.1);
            border-color: rgba(64,153,255,.2);
        }

        .feat-card-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, rgba(64,153,255,.08), rgba(46,216,182,.08));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            margin-bottom: 1rem;
            transition: all .4s ease;
        }

        .feat-card-icon svg {
            width: 22px;
            height: 22px;
        }

        .feat-card:hover .feat-card-icon {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            transform: scale(1.1) rotate(-5deg);
            box-shadow: 0 10px 25px rgba(64,153,255,.2);
        }

        .feat-card h4 {
            font-size: 1.05rem;
            color: var(--text-primary);
            margin-bottom: .5rem;
            font-weight: 700;
        }

        .feat-card p {
            color: var(--text-secondary);
            font-size: .9rem;
            line-height: 1.6;
            margin: 0;
        }

        .feat-section-title {
            text-align: center;
            font-size: 2.2rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .feat-section-sub {
            text-align: center;
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .roi-section {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            padding: 5rem 2rem;
            border-radius: 24px;
            margin: 5rem 0;
            text-align: center;
        }

        .roi-section h3 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 3rem;
        }

        .roi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .roi-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            transition: transform .3s ease;
        }

        .roi-item:hover {
            transform: translateY(-4px);
        }

        .roi-number {
            font-size: 2.8rem;
            font-weight: 900;
            margin-bottom: .5rem;
            letter-spacing: -1px;
        }

        .roi-label {
            font-size: .95rem;
            opacity: .9;
            line-height: 1.5;
        }

        .roles-section {
            margin: 5rem 0;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            padding: 2rem 1.5rem;
            border-radius: 20px;
            text-align: center;
            transition: transform .4s ease, box-shadow .4s ease;
        }

        .role-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px rgba(64,153,255,.3);
        }

        .role-card-icon {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,.15);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .role-card-icon svg {
            width: 24px;
            height: 24px;
            color: #fff;
        }

        .role-card h4 {
            font-size: 1.05rem;
            margin-bottom: .4rem;
            font-weight: 700;
        }

        .role-card p {
            font-size: .85rem;
            opacity: .9;
            line-height: 1.5;
            margin: 0;
        }

        .role-card p small {
            display: block;
            font-size: .8rem;
            opacity: .8;
            margin-top: .3rem;
        }

        .investor-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: #fff;
            padding: 3rem 2rem;
            border-radius: 24px;
            text-align: center;
            margin: 5rem 0;
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.8;
            box-shadow: 0 20px 50px rgba(64,153,255,.3);
        }

        .investor-card svg {
            width: 28px;
            height: 28px;
            vertical-align: middle;
            margin-right: .5rem;
        }

        .features-cta {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: #fff;
            padding: 5rem 2rem;
            border-radius: 24px;
            text-align: center;
            margin: 5rem 0;
        }

        .features-cta h3 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .features-cta p {
            font-size: 1.1rem;
            opacity: .9;
            margin-bottom: 2.5rem;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .cta-btn-primary {
            padding: .9rem 2.5rem;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all .3s ease;
            display: inline-block;
            background: #fff;
            color: var(--primary);
            border: none;
        }

        .cta-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,.2);
        }

        .cta-btn-outline {
            padding: .9rem 2.5rem;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            transition: all .3s ease;
            display: inline-block;
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,.6);
        }

        .cta-btn-outline:hover {
            background: rgba(255,255,255,.1);
            border-color: #fff;
        }

        @media (max-width: 1024px) {
            .features-layout {
                flex-direction: column;
                padding: 2rem 1.2rem;
                gap: 1rem;
            }

            .features-sidebar {
                width: 100%;
                position: static;
                max-height: none;
                overflow-y: visible;
                display: flex;
                flex-wrap: nowrap;
                gap: .4rem;
                padding: .8rem .8rem;
                border-radius: 16px;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .sidebar-link {
                flex-shrink: 0;
                white-space: nowrap;
                padding: .45rem .7rem;
                font-size: .78rem;
                width: auto;
            }
        }

        @media (max-width: 768px) {
            .features-hero {
                margin-top: 80px;
                padding: 6rem 1.5rem 4rem;
            }

            .features-hero-content h1 {
                font-size: 2.2rem;
            }

            .features-hero-subtitle {
                font-size: 1.05rem;
            }

            .features-hero-tagline {
                font-size: .85rem;
                padding: .8rem 1.2rem;
            }

            .feat-header h2 {
                font-size: 1.5rem;
            }

            .feat-header-icon {
                width: 52px;
                height: 52px;
            }

            .feat-header-icon svg {
                width: 22px;
                height: 22px;
            }

            .features-sidebar .sidebar-link span {
                display: none;
            }

            .features-container {
                padding: 2rem 1.2rem;
            }

            .feat-section-title {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 480px) {
            .feat-grid {
                grid-template-columns: 1fr;
            }

            .roles-grid {
                grid-template-columns: 1fr;
            }

            .roi-grid {
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
        ['href' => 'index.php#pricing', 'label' => 'Pricing'],
        ['href' => 'index.php#testimonials', 'label' => 'Reviews'],
        ['href' => 'index.php#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php'; 
    ?>

    <div class="features-wrapper">
        <section class="features-hero">
            <div class="features-hero-content">
                <h1>Complete Feature Breakdown</h1>
                <p class="features-hero-subtitle">An all-in-one, automation-first SaaS platform purpose-built for modern travel agencies</p>
                <div class="features-hero-tagline">
                    Ticketing &middot; Umrah &middot; Visas &middot; Hotels &middot; Finance &middot; Automation &middot; Reporting &middot; Client Portal
                </div>
            </div>
        </section>

        <div class="features-layout">
            <aside class="features-sidebar" id="featuresSidebar">
                <button class="sidebar-link active" data-target="ticketing">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 7V5"/><path d="M17 7V5"/></svg>
                    <span>Ticketing</span>
                </button>
                <button class="sidebar-link" data-target="umrah">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    <span>Umrah</span>
                </button>
                <button class="sidebar-link" data-target="visa">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    <span>Visa &amp; Hotels</span>
                </button>
                <button class="sidebar-link" data-target="finance">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span>Finance</span>
                </button>
                <button class="sidebar-link" data-target="dashboards">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                    <span>Dashboards</span>
                </button>
                <button class="sidebar-link" data-target="automation">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    <span>Automation</span>
                </button>
                <button class="sidebar-link" data-target="multibranch">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l5-4v18"/><path d="M19 21V7l-5-4v18"/><path d="M9 21V11"/><path d="M15 21V11"/></svg>
                    <span>Multi-Branch</span>
                </button>
                <button class="sidebar-link" data-target="maktob">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Maktob</span>
                </button>
                <button class="sidebar-link" data-target="hr">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <span>HR</span>
                </button>
                <button class="sidebar-link" data-target="communication">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    <span>Communication</span>
                </button>
                <button class="sidebar-link" data-target="security">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Security</span>
                </button>
                <button class="sidebar-link" data-target="portal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Portal</span>
                </button>
                <button class="sidebar-link" data-target="learning">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    <span>Learning</span>
                </button>
            </aside>

            <div class="features-content">

            <div class="features-container">

            <!-- Ticketing -->
            <section class="feature-category" id="ticketing" data-category="ticketing">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 7V5"/><path d="M17 7V5"/></svg>
                    </div>
                    <h2>Ticketing &amp; Reservations</h2>
                </div>
                <p class="feat-intro">Agencies juggle scattered ticket records across multiple suppliers with no central truth. MTravels gives you a complete booking engine with automated profit tracking, refunds, date changes, baggage management, and OCR-powered document processing.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 7V5"/><path d="M17 7V5"/></svg>
                        </div>
                        <h4>Ticket Bookings</h4>
                        <p>Complete ticket booking system with real-time availability</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h4>Ticket Reservations</h4>
                        <p>On-hold sales with automatic expiration and follow-up</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </div>
                        <h4>Refunded Tickets</h4>
                        <p>Complete refund processing with financial reconciliation</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h4>Date Change Tickets</h4>
                        <p>Manage date changes with penalty calculations and notifications</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <h4>Ticket Weight Management</h4>
                        <p>Track baggage allowances and weight limits with pricing</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Automatic Profit Calculation</h4>
                        <p>Real-time profit calculation per ticket with margin tracking</p>
                    </div>
                </div>
            </section>

            <!-- Umrah -->
            <section class="feature-category" id="umrah" data-category="umrah">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                    </div>
                    <h2>Umrah &amp; Family Management</h2>
                </div>
                <p class="feat-intro">Managing family groups, individual member tracking, separate payments per traveler, and cancellations is chaotic in spreadsheets. MTravels handles the entire Umrah lifecycle — family bookings, member-level tracking, individual receipts, agreements, ID cards, and passport OCR.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h4>Family-Based Booking</h4>
                        <p>Group families and manage all members with individual preferences</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4>Member Management</h4>
                        <p>Track each traveler with their own tickets, visas, and accommodations</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        </div>
                        <h4>Individual Payments</h4>
                        <p>Collect payments per member with family-level financial visibility</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Family Transactions</h4>
                        <p>Complete financial tracking for each family unit</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <h4>Cancellations &amp; Refunds</h4>
                        <p>Manage cancellations with automatic refund calculations</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>Agreements &amp; ID Cards</h4>
                        <p>Generate Umrah agreements and pilgrim ID cards automatically</p>
                    </div>
                </div>
            </section>

            <!-- Visa & Hotels -->
            <section class="feature-category" id="visa" data-category="visa">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                    </div>
                    <h2>Visa &amp; Hotel Management</h2>
                </div>
                <p class="feat-intro">Visa and hotel processes handled outside the system create data gaps and reconciliation nightmares. MTravels brings everything in-house — visa applications, hotel bookings, refunds, cancellations, client/supplier account linkage, and automated financial impact tracking.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        </div>
                        <h4>Visa Applications</h4>
                        <p>Complete visa application management with document tracking</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Visa Transactions</h4>
                        <p>Track all visa-related financial transactions</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </div>
                        <h4>Visa Refunds</h4>
                        <p>Process visa refunds with automatic reconciliation</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        </div>
                        <h4>Visa Cancellations</h4>
                        <p>Manage visa cancellations with status updates</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4>Client &amp; Supplier Tracking</h4>
                        <p>Track clients and suppliers for each application</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Status Tracking</h4>
                        <p>Monitor visa and hotel status with automated updates</p>
                    </div>
                </div>
                <div class="feat-grid" style="margin-top: 1.5rem;">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M3 7v14"/><path d="M21 7v14"/><path d="M3 7l9-5 9 5"/></svg>
                        </div>
                        <h4>Hotel Bookings</h4>
                        <p>Complete hotel booking management with room types and rates</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </div>
                        <h4>Hotel Refunds</h4>
                        <p>Process hotel refunds with automatic reconciliation</p>
                    </div>
                </div>
            </section>

            <!-- Finance -->
            <section class="feature-category" id="finance" data-category="finance">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <h2>Finance &amp; Accounting</h2>
                </div>
                <p class="feat-intro">Poor visibility into cash flow, profit sources, and outstanding dues costs agencies real money. MTravels provides multi-currency financial management with real-time P&amp;L, account tracking, debtor/creditor management, salary, asset, expense management, and comprehensive financial statements.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </div>
                        <h4>Multi-Currency Support</h4>
                        <p>AFN, USD, AED, EUR with real-time conversion</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Real-Time P&amp;L</h4>
                        <p>Automatic profit and loss across all operations</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        </div>
                        <h4>Main Accounts</h4>
                        <p>Safe, Bank, Sarafi accounts with complete tracking</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4>Client &amp; Supplier Ledgers</h4>
                        <p>Complete ledgers for clients and suppliers</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <h4>Debtors &amp; Creditors</h4>
                        <p>Track outstanding amounts and manage collections</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        </div>
                        <h4>Sarafi Management</h4>
                        <p>Money exchange management with rate tracking</p>
                    </div>
                </div>
            </section>

            <!-- Dashboards -->
            <section class="feature-category" id="dashboards" data-category="dashboards">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                    </div>
                    <h2>Dashboards &amp; Reporting</h2>
                </div>
                <p class="feat-intro">Static reports don't help owners make fast decisions. MTravels gives you real-time dashboards with multi-currency charts, profit breakdowns, outstanding dues tracking, user performance metrics, and exportable Excel/PDF reports.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Admin Dashboard</h4>
                        <p>Comprehensive dashboard with all key metrics at a glance</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Multi-Currency Cash Flow</h4>
                        <p>Cash flow visualisation with multi-currency support</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h4>Time Period Filters</h4>
                        <p>Daily, monthly, and yearly data filtering</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                        <h4>Profit Cards</h4>
                        <p>Today, month, and year performance at a glance</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <h4>Drill-Down Profit View</h4>
                        <p>Dig from profit sources to individual items</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>Outstanding Dues</h4>
                        <p>Track client pending payments and overdue amounts</p>
                    </div>
                </div>
            </section>

            <!-- Automation -->
            <section class="feature-category" id="automation" data-category="automation">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
                    <h2>Automation &amp; Intelligence</h2>
                </div>
                <p class="feat-intro">Manual data entry and repetitive communication consume hours daily. MTravels automates profit calculations, email/WhatsApp notifications, OCR document processing, and provides real-time analytics with interactive charts.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Automated Profit Calculation</h4>
                        <p>Auto-calculated profit for tickets, visas, hotels, and Umrah</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Real-Time Analytics</h4>
                        <p>Interactive dashboard with live data visualisation</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <h4>Interactive Charts</h4>
                        <p>Visual charts for financial and operational data</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
                        </div>
                        <h4>Email Automation</h4>
                        <p>Automated branded emails for tickets, visas, hotels, and invoices</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h4>WhatsApp Automation</h4>
                        <p>Tenant-configurable WhatsApp messaging with templates</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <h4>OCR &amp; Auto-Fill</h4>
                        <p>Auto-extract passenger data from tickets and passports</p>
                    </div>
                </div>
            </section>

            <!-- Multi-Branch -->
            <section class="feature-category" id="multibranch" data-category="multibranch">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l5-4v18"/><path d="M19 21V7l-5-4v18"/><path d="M9 21V11"/><path d="M15 21V11"/></svg>
                    </div>
                    <h2>Multi-Branch Management</h2>
                </div>
                <p class="feat-intro">Growing agencies duplicate chaos instead of scaling systems. MTravels lets you manage unlimited branches under one account — with separate users, permissions, branding, and performance tracking per branch.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l5-4v18"/><path d="M19 21V7l-5-4v18"/><path d="M9 21V11"/><path d="M15 21V11"/></svg>
                        </div>
                        <h4>Unlimited Branches</h4>
                        <p>Add unlimited branches under one account — each with its own users, clients, and operations</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h4>Data Isolation</h4>
                        <p>Complete data separation between branches — each branch sees only its own data</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Branch Dashboard</h4>
                        <p>Compare performance across branches and export reports per branch</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
                        </div>
                        <h4>Shared Communication</h4>
                        <p>SMTP and WhatsApp settings are configured at the account level — all branches share the same channels with unified branding</p>
                    </div>
                </div>
            </section>

            <!-- Maktob -->
            <section class="feature-category" id="maktob" data-category="maktob">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    </div>
                    <h2>Maktob Management</h2>
                </div>
                <p class="feat-intro">Lost official letters, version confusion, and compliance gaps are common when correspondence is managed manually. Maktob provides systematic numbering, multi-language support (English, Dari, Pashto), PDF generation, and full audit logging.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>Issued &amp; Received Letters</h4>
                        <p>Track both incoming and outgoing official correspondence</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                        </div>
                        <h4>Automatic Numbering</h4>
                        <p>Systematic Maktob numbering for easy reference</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </div>
                        <h4>Multi-Language</h4>
                        <p>Create letters in English, Dari, and Pashto</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>PDF Generation</h4>
                        <p>Professional PDF documents with preview capability</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h4>Status Management</h4>
                        <p>Draft, Sent, Archived tracking with search and filters</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l5-4v18"/><path d="M19 21V7l-5-4v18"/><path d="M9 21V11"/><path d="M15 21V11"/></svg>
                        </div>
                        <h4>Branch-Aware</h4>
                        <p>Letters managed with branch-specific context and access</p>
                    </div>
                </div>
            </section>

            <!-- HR -->
            <section class="feature-category" id="hr" data-category="hr">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h2>HR &amp; Attendance</h2>
                </div>
                <p class="feat-intro">Tracking employee attendance, calculating payroll, and managing performance across multiple branches is time-consuming and error-prone. MTravels HR module handles attendance, branch-level tracking, salary integration, and performance analytics.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <h4>Employee Attendance</h4>
                        <p>Track check-in/check-out times per employee</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l5-4v18"/><path d="M19 21V7l-5-4v18"/></svg>
                        </div>
                        <h4>Branch-Level Tracking</h4>
                        <p>Manage attendance separately per branch</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Salary Integration</h4>
                        <p>Auto-calculate salary based on attendance and performance</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Performance Reporting</h4>
                        <p>Generate reports based on attendance and KPIs</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <h4>Employee Records</h4>
                        <p>Complete profiles with attendance history</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </div>
                        <h4>Productivity Analytics</h4>
                        <p>Analyze attendance patterns and productivity trends</p>
                    </div>
                </div>
            </section>

            <!-- Communication -->
            <section class="feature-category" id="communication" data-category="communication">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h2>Communication &amp; Collaboration</h2>
                </div>
                <p class="feat-intro">Agencies need seamless communication between branches and with partner agencies. MTravels provides built-in inter-tenant chat, shared agreements with controlled access, and collaboration tools for cross-agency ticket/visa sales.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h4>Inter-Tenant Chat</h4>
                        <p>Real-time messaging between different agencies</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <h4>Business Collaboration</h4>
                        <p>Coordinate ticket, visa, and Umrah sales between agencies</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>Shared Agreements</h4>
                        <p>Create agreements with controlled access permissions</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h4>Controlled Access</h4>
                        <p>Manage permissions for shared communications</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="20" x2="21" y2="20"/><line x1="5" y1="16" x2="5" y2="20"/><line x1="10" y1="12" x2="10" y2="20"/><line x1="15" y1="8" x2="15" y2="20"/><line x1="20" y1="4" x2="20" y2="20"/></svg>
                        </div>
                        <h4>Collaboration Analytics</h4>
                        <p>Track collaboration activities and outcomes</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        </div>
                        <h4>Mobile Collaboration</h4>
                        <p>Access collaboration tools from any device</p>
                    </div>
                </div>
            </section>

            <!-- Security -->
            <section class="feature-category" id="security" data-category="security">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <h2>Security &amp; Audit Logs</h2>
                </div>
                <p class="feat-intro">Financial systems demand complete accountability. Every transaction in MTravels is fully auditable — who changed what, when, and from where. Enterprise-grade AES-256 encryption, role-based access, automated backups, and compliance reporting built in.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>Comprehensive Audit Logs</h4>
                        <p>Full transaction history — who changed what and when</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h4>Role-Based Security</h4>
                        <p>Secure actions only by appropriate roles</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <h4>Change History</h4>
                        <p>Complete history of sensitive operations for compliance</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <h4>Data Encryption</h4>
                        <p>AES-256 encryption for all data at rest and in transit</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h4>Automated Backups</h4>
                        <p>Daily backups with point-in-time recovery</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <h4>Compliance Reports</h4>
                        <p>Generate audit-ready compliance reports automatically</p>
                    </div>
                </div>
            </section>

            <!-- Portal -->
            <section class="feature-category" id="portal" data-category="portal">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h2>Client Portal</h2>
                </div>
                <p class="feat-intro">Clients constantly ask for booking status, ticket copies, and invoices — wasting staff time on repetitive requests. MTravels Client Portal gives customers 24/7 self-service access to their booking history, documents, and real-time status updates.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h4>Client Login Access</h4>
                        <p>Secure authentication for clients to access their accounts</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 7V5"/><path d="M17 7V5"/></svg>
                        </div>
                        <h4>Booking History</h4>
                        <p>Complete history of all tickets and services</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Balance &amp; Transactions</h4>
                        <p>View account balance and transaction history</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </div>
                        <h4>Document Downloads</h4>
                        <p>Download invoices, tickets, and documents anytime</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg>
                        </div>
                        <h4>Mobile Friendly</h4>
                        <p>Fully responsive design for phones and tablets</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </div>
                        <h4>Real-Time Notifications</h4>
                        <p>Automatic push notifications for booking updates</p>
                    </div>
                </div>
            </section>

            <!-- Learning -->
            <section class="feature-category" id="learning" data-category="learning">
                <div class="feat-header">
                    <div class="feat-header-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
                    </div>
                    <h2>Onboarding &amp; Learning</h2>
                </div>
                <p class="feat-intro">New users struggle with complex systems. MTravels includes a built-in learning system with Vimeo-hosted tutorials, step-by-step guides, role-based learning paths, feature-specific help videos, and a support ticket system — all inside the platform.</p>
                <div class="feat-grid">
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                        </div>
                        <h4>Video Tutorials</h4>
                        <p>Fast, reliable Vimeo-hosted training content</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        </div>
                        <h4>Step-by-Step Guides</h4>
                        <p>Written guides for every feature and workflow</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                        </div>
                        <h4>Role-Based Learning</h4>
                        <p>Customized paths for admin, sales, and finance roles</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <h4>Contextual Help</h4>
                        <p>Feature-specific help available right in the interface</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                        <h4>Quick Wins Series</h4>
                        <p>Short getting-started videos for common tasks</p>
                    </div>
                    <div class="feat-card">
                        <div class="feat-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        </div>
                        <h4>Support Tickets</h4>
                        <p>Lightweight built-in support with SLA tracking</p>
                    </div>
                </div>
            </section>

            <!-- ROI -->
            <section class="roi-section">
                <h3>Measurable Business Impact</h3>
                <div class="roi-grid">
                    <div class="roi-item">
                        <div class="roi-number">80%</div>
                        <div class="roi-label">Reduction in manual work &amp; data entry errors</div>
                    </div>
                    <div class="roi-item">
                        <div class="roi-number">40%</div>
                        <div class="roi-label">Fewer customer support requests</div>
                    </div>
                    <div class="roi-item">
                        <div class="roi-number">3x</div>
                        <div class="roi-label">Faster booking processing</div>
                    </div>
                    <div class="roi-item">
                        <div class="roi-number">45%</div>
                        <div class="roi-label">Increase in customer satisfaction</div>
                    </div>
                </div>
            </section>

            <!-- Roles -->
            <section class="roles-section">
                <h3 class="feat-section-title">Roles &amp; Permissions</h3>
                <p class="feat-section-sub">Clear, role-based access aligned with real agency operations</p>
                <div class="roles-grid">
                    <div class="role-card">
                        <div class="role-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <h4>Tenant Super Admin</h4>
                        <p>Agency Owner<small>Global dashboard, branch comparison, reports</small></p>
                    </div>
                    <div class="role-card">
                        <div class="role-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        </div>
                        <h4>Admin</h4>
                        <p>System Management<small>Users, settings, audit logs</small></p>
                    </div>
                    <div class="role-card">
                        <div class="role-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <h4>Finance</h4>
                        <p>Accounting<small>Ledgers, invoices, payments, reports</small></p>
                    </div>
                    <div class="role-card">
                        <div class="role-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                        </div>
                        <h4>Sales</h4>
                        <p>Booking Team<small>Bookings, clients, quotes</small></p>
                    </div>
                    <div class="role-card">
                        <div class="role-card-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
                        </div>
                        <h4>Umrah Team</h4>
                        <p>Pilgrimage Ops<small>Families, members, payments, visas</small></p>
                    </div>
                </div>
            </section>



            <!-- Investor -->
            <div class="investor-card">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                An all-in-one, automation-first, multi-branch travel agency SaaS covering ticketing, Umrah, visas, hotels, finance, communication, reporting, and client management — built for real agency operations, not theory.
            </div>

            <!-- CTA -->
            <section class="features-cta">
                <h3>Ready to Revolutionize Your Travel Agency?</h3>
                <p>Start your free 14-day trial today. No credit card required. Full access to all features.</p>
                <div class="cta-buttons">
                    <a href="book-demo.php" class="cta-btn-primary">Schedule Demo</a>
                    <a href="index.php#pricing" class="cta-btn-outline">View Pricing</a>
                </div>
            </section>

            </div>
        </div>
    </div>
</div>

    <script>
        // Sidebar scroll-spy with IntersectionObserver
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.feature-category');
            const sidebarLinks = document.querySelectorAll('.sidebar-link');

            if (!sections.length || !sidebarLinks.length) return;

            const observer = new IntersectionObserver((entries) => {
                let activeId = '';
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        activeId = entry.target.id;
                    }
                });
                if (activeId) {
                    sidebarLinks.forEach(link => {
                        link.classList.toggle('active', link.dataset.target === activeId);
                    });
                }
            }, {
                rootMargin: '-80px 0px -20% 0px',
                threshold: 0
            });

            sections.forEach(section => observer.observe(section));

            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    const target = document.getElementById(this.dataset.target);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        });

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
