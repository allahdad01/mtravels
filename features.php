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

        .features-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Advanced Hero */
        .advanced-hero {
            position: relative;
            padding: 8rem 2rem 5rem 2rem;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            overflow: hidden;
            text-align: center;
            margin-top: 150px;
            z-index: 1;
        }

        .advanced-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .advanced-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 1000px;
            margin: 0 auto;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            opacity: 0.95;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hero-tagline {
            background: rgba(255, 255, 255, 0.2);
            padding: 1.5rem;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 2rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* Feature Navigation Tabs */
        .feature-nav {
            display: flex;
            justify-content: center;
            gap: 0.8rem;
            flex-wrap: wrap;
            padding: 3rem 2rem;
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border);
            transition: background 0.3s ease;
        }

        .feature-nav button {
            padding: 0.75rem 1.5rem;
            border: 2px solid var(--border);
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .feature-nav button:hover,
        .feature-nav button.active {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.3);
        }

        /* Features Container */
        .features-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .feature-category {
            margin-bottom: 5rem;
            animation: fadeInUp 0.6s ease-out;
        }

        .feature-category.hidden {
            display: none;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .category-header {
            margin-bottom: 3rem;
        }

        .category-title {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .category-icon {
            font-size: 3rem;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .category-title h2 {
            font-size: 2.2rem;
            color: var(--text-primary);
            margin: 0;
        }

        .problem-solution {
            background: var(--bg-surface);
            padding: 2.5rem;
            border-radius: 12px;
            margin-bottom: 2.5rem;
            border-left: 4px solid #ef4444;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: background 0.3s ease;
        }

        html.dark-mode .problem-solution {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .problem-solution h3 {
            font-size: 1.2rem;
            color: #ef4444;
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .problem-solution h3::before {
            content: '⚠️';
            font-size: 1.3rem;
        }

        .problem-text {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .solution-heading {
            font-size: 1.1rem;
            color: #10b981;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .solution-heading::before {
            content: '✓';
            font-size: 1.3rem;
        }

        .solution-text {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Capabilities Grid */
        .capabilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .capability-card {
            background: var(--bg-secondary);
            padding: 1.8rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.320, 1);
            position: relative;
            overflow: hidden;
        }

        .capability-card::before {
            content: '';
            position: absolute;
            width: 4px;
            height: 0%;
            background: linear-gradient(180deg, #4099ff, #2ed8b6);
            left: 0;
            top: 0;
            transition: height 0.4s ease;
        }

        .capability-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(64, 153, 255, 0.15);
            border-color: #4099ff;
        }

        .capability-card:hover::before {
            height: 100%;
        }

        .capability-icon {
            font-size: 1.8rem;
            margin-bottom: 0.8rem;
        }

        .capability-card h4 {
            font-size: 1.05rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .capability-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Detailed Features List */
        .features-list {
            background: var(--bg-surface);
            padding: 2rem;
            border-radius: 12px;
            margin-top: 2rem;
            transition: background 0.3s ease;
        }

        .features-list h4 {
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            font-weight: 700;
        }

        .features-list ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.2rem;
        }

        .features-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.8rem;
            padding: 0.8rem;
            background: var(--bg-secondary);
            border-radius: 8px;
            border-left: 3px solid #4099ff;
            transition: background 0.3s ease;
        }

        .features-list li::before {
            content: '✓';
            color: #4099ff;
            font-weight: 700;
            font-size: 1.2rem;
            flex-shrink: 0;
            margin-top: -2px;
        }

        .features-list li span {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* ROI Section */
        .roi-section {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            padding: 4rem 2rem;
            border-radius: 16px;
            margin: 5rem 0;
            text-align: center;
        }

        .roi-section h3 {
            font-size: 2rem;
            margin-bottom: 3rem;
        }

        .roi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .roi-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .roi-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .roi-label {
            font-size: 0.95rem;
            opacity: 0.95;
            line-height: 1.5;
        }

        /* Investor Tagline */
        .investor-tagline {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            padding: 3rem 2rem;
            border-radius: 16px;
            text-align: center;
            margin: 5rem 0;
            font-size: 1.3rem;
            font-weight: 600;
            line-height: 1.8;
            box-shadow: 0 20px 50px rgba(64, 153, 255, 0.3);
        }

        /* Architecture Section */
        .architecture-section {
            background: var(--bg-surface);
            padding: 3rem 2rem;
            border-radius: 12px;
            margin: 5rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: background 0.3s ease;
        }

        html.dark-mode .architecture-section {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .architecture-section h3 {
            font-size: 1.8rem;
            color: var(--text-primary);
            margin-bottom: 2rem;
        }

        .architecture-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .arch-item {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: background 0.3s ease;
        }

        .arch-item h4 {
            font-size: 1.1rem;
            color: #4099ff;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .arch-item p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Roles Section */
        .roles-section {
            background: var(--bg-surface);
            padding: 3rem 2rem;
            border-radius: 12px;
            margin: 5rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: background 0.3s ease;
        }

        html.dark-mode .roles-section {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .roles-section h3 {
            font-size: 1.8rem;
            color: var(--text-primary);
            margin-bottom: 2rem;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .role-card {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
        }

        .role-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .role-card h4 {
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            font-weight: 700;
        }

        .role-card p {
            font-size: 0.9rem;
            opacity: 0.95;
            line-height: 1.5;
        }

        /* CTA Section */
        .advanced-cta {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            margin: 5rem 0;
        }

        .advanced-cta h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .advanced-cta p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-primaryi, .btn-secondaryi {
            padding: 0.9rem 2.5rem;
            border: 2px solid transparent;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primaryi {
            background: white;
            color: var(--primary);
        }

        .btn-primaryi:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .btn-secondaryi {
            background: transparent;
            color: white;
            border-color: white;
        }

        .btn-secondaryi:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .advanced-hero {
                margin-top: 80px;
            }

            .advanced-hero h1 {
                font-size: 2.2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .category-title h2 {
                font-size: 1.6rem;
            }

            .category-icon {
                width: 60px;
                height: 60px;
                font-size: 2rem;
            }

            .feature-nav button {
                padding: 0.6rem 1rem;
                font-size: 0.8rem;
            }

            .problem-solution {
                padding: 1.5rem;
            }

            .investor-tagline {
                font-size: 1.1rem;
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
        <!-- Hero -->
        <section class="advanced-hero">
            <div class="hero-content">
                <h1>Complete Feature Breakdown</h1>
                <p class="hero-subtitle">An all-in-one, automation-first SaaS platform purpose-built for modern travel agencies</p>
                <div class="hero-tagline">
                    Covering ticketing, Umrah, visas, hotels, finance, automation, reporting, supplier, and client management
                </div>
            </div>
        </section>

        <!-- Feature Navigation -->
        <div class="feature-nav">
            <button class="nav-tab active" data-category="ticketing">🎫 Ticketing</button>
            <button class="nav-tab" data-category="umrah">🕋 Umrah</button>
            <button class="nav-tab" data-category="visa">🛂 Visa & Hotels</button>
            <button class="nav-tab" data-category="finance">💰 Finance</button>
            <button class="nav-tab" data-category="dashboards">📊 Dashboards</button>
            <button class="nav-tab" data-category="automation">🤖 Automation</button>
            <button class="nav-tab" data-category="multibranch">🏢 Multi-Branch</button>
            <button class="nav-tab" data-category="maktob">🧾 Maktob</button>
            <button class="nav-tab" data-category="hr">🕒 HR & Attendance</button>
            <button class="nav-tab" data-category="communication">💬 Communication</button>
            <button class="nav-tab" data-category="security">🔐 Security</button>
            <button class="nav-tab" data-category="portal">👥 Portal</button>
            <button class="nav-tab" data-category="learning">🎓 Learning</button>
        </div>

        <!-- Features Container -->
        <div class="features-container">

            <!-- 1. Ticketing & Reservations -->
            <section class="feature-category" data-category="ticketing">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🧳</div>
                        <h2>Ticketing & Reservations (Core Engine)</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Travel agencies struggle with scattered ticket records, manual tracking across different suppliers, unclear profitability per ticket, and difficulty managing date changes and refunds.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete ticket booking system with reservations, refunds, date changes, weight management, and automated profit calculation. Includes supplier, client, and internal account tracking with OCR document handling.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">🎫</div>
                        <h4>Ticket Bookings</h4>
                        <p>Complete ticket booking system with real-time availability</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📅</div>
                        <h4>Ticket Reservations</h4>
                        <p>On-hold sales with automatic expiration and follow-up</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔄</div>
                        <h4>Refunded Tickets</h4>
                        <p>Complete refund processing with financial reconciliation</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📆</div>
                        <h4>Date Change Tickets</h4>
                        <p>Manage date changes with penalty calculations and notifications</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">⚖️</div>
                        <h4>Ticket Weight Management</h4>
                        <p>Track baggage allowances and weight limits with pricing</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Automatic Profit Calculation</h4>
                        <p>Real-time profit calculation per ticket with margin tracking</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Ticket bookings and reservations</span></li>
                        <li><span>Refunded tickets processing</span></li>
                        <li><span>Date change tickets with penalty management</span></li>
                        <li><span>Ticket weight management and baggage tracking</span></li>
                        <li><span>Automatic profit calculation per ticket</span></li>
                        <li><span>Supplier, client, and internal account tracking</span></li>
                        <li><span>Ticket PDF reader with auto-extract passenger & flight data</span></li>
                        <li><span>Passport OCR reader for auto-fill passenger details</span></li>
                        <li><span>Ticket-wise and period-wise reporting</span></li>
                    </ul>
                </div>
            </section>

            <!-- 2. Umrah & Family Management -->
            <section class="feature-category hidden" data-category="umrah">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🕋</div>
                        <h2>Umrah & Family Management</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Managing Umrah families, individual member tracking, separate payments per traveler, and handling cancellations is complex, error-prone, and impossible to do properly in spreadsheets.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete Umrah pilgrimage booking system with family-based management, member tracking, individual payments, refunds, agreements generation, ID card creation, and passport OCR for pilgrims.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">👨‍👩‍👧‍👦</div>
                        <h4>Family-Based Umrah Booking</h4>
                        <p>Group families and manage all members with individual preferences</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">👤</div>
                        <h4>Member Management</h4>
                        <p>Track each traveler within families with their own tickets, visas, and accommodations</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💳</div>
                        <h4>Individual Payments & Receipts</h4>
                        <p>Collect and track payments individually while maintaining family-level financial visibility</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Family Transaction Tracking</h4>
                        <p>Complete financial tracking for each family unit</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">❌</div>
                        <h4>Umrah Cancellations & Refunds</h4>
                        <p>Manage cancellations and process refunds with automatic calculations</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📄</div>
                        <h4>Agreements & ID Cards</h4>
                        <p>Generate Umrah agreements and ID cards automatically</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Family-based Umrah booking system</span></li>
                        <li><span>Member management per family</span></li>
                        <li><span>Individual member payments & receipts</span></li>
                        <li><span>Family transaction tracking</span></li>
                        <li><span>Umrah cancellations and refunds</span></li>
                        <li><span>Umrah agreements generation</span></li>
                        <li><span>ID card generation for pilgrims</span></li>
                        <li><span>Passport OCR for Umrah pilgrims</span></li>
                        <li><span>Multi-currency Umrah payments</span></li>
                        <li><span>Bank receipt & payment tracking</span></li>
                    </ul>
                </div>
            </section>

            <!-- 3. Visa & Hotel Management -->
            <section class="feature-category hidden" data-category="visa">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🛂</div>
                        <h2>Visa Management</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Visa processes are often handled outside the system, causing data gaps, missed deadlines, and unclear financial impact on agency accounts.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete visa management system with applications, transactions, refunds, cancellations, client and supplier tracking, status tracking, and automated client notifications.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">📋</div>
                        <h4>Visa Applications</h4>
                        <p>Complete visa application management with document tracking</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Visa Transactions</h4>
                        <p>Track all visa-related financial transactions</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔄</div>
                        <h4>Visa Refunds</h4>
                        <p>Process visa refunds with automatic financial reconciliation</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">❌</div>
                        <h4>Visa Cancellations</h4>
                        <p>Manage visa cancellations with status updates</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">👥</div>
                        <h4>Client & Supplier Tracking</h4>
                        <p>Track clients and suppliers for each visa application</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Visa Status Tracking</h4>
                        <p>Monitor visa status with automated updates</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Visa applications management</span></li>
                        <li><span>Visa transactions tracking</span></li>
                        <li><span>Visa refunds processing</span></li>
                        <li><span>Visa cancellations management</span></li>
                        <li><span>Client and supplier tracking</span></li>
                        <li><span>Visa status tracking</span></li>
                        <li><span>Automated client notifications</span></li>
                    </ul>
                </div>
            </section>

            <!-- 4. Hotel Management -->
            <section class="feature-category hidden" data-category="hotel">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🏨</div>
                        <h2>Hotel Management</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Hotel bookings are often managed separately, causing reconciliation issues and unclear financial impact.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete hotel booking and refund system with client & supplier account linkage and automated financial impact tracking.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">🏨</div>
                        <h4>Hotel Bookings</h4>
                        <p>Complete hotel booking management with room types and rates</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔄</div>
                        <h4>Hotel Refunds</h4>
                        <p>Process hotel refunds with automatic financial reconciliation</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">👥</div>
                        <h4>Client & Supplier Accounts</h4>
                        <p>Link hotel bookings to client and supplier accounts</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Automated Financial Impact</h4>
                        <p>Automatically update accounts based on hotel transactions</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Hotel bookings management</span></li>
                        <li><span>Hotel refunds processing</span></li>
                        <li><span>Client & supplier account linkage</span></li>
                        <li><span>Automated financial impact on accounts</span></li>
                    </ul>
                </div>
            </section>

            <!-- 5. Finance & Accounting -->
            <section class="feature-category hidden" data-category="finance">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">💰</div>
                        <h2>Finance & Accounting (Very Strong)</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Agencies lose money due to poor visibility into cash flow, profit sources, outstanding dues, supplier reconciliation, and manual accounting errors that compound over time.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete financial management system with multi-currency support, real-time P&L calculation, main accounts, client/supplier tracking, JV payments, salary management, asset management, expense management, and comprehensive financial statements.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">🌍</div>
                        <h4>Multi-Currency Support</h4>
                        <p>Support for AFN, USD, AED, EUR with real-time conversion</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Real-Time P&L Calculation</h4>
                        <p>Automatic profit and loss calculation across all operations</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🏦</div>
                        <h4>Main Accounts</h4>
                        <p>Manage Safe, Bank, Sarafi accounts with complete tracking</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">👥</div>
                        <h4>Client & Supplier Accounts</h4>
                        <p>Complete ledgers for clients and suppliers</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Debtors & Creditors</h4>
                        <p>Track outstanding amounts and manage collections</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔄</div>
                        <h4>Sarafi Management</h4>
                        <p>Money exchange management with rate tracking</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Multi-currency support (AFN, USD, AED, EUR)</span></li>
                        <li><span>Real-time profit & loss calculation</span></li>
                        <li><span>Main accounts (Safe, Bank, Sarafi)</span></li>
                        <li><span>Client accounts management</span></li>
                        <li><span>Supplier accounts management</span></li>
                        <li><span>Debtors management</span></li>
                        <li><span>Creditors management</span></li>
                        <li><span>Sarafi (money exchange) management</span></li>
                        <li><span>JV (Joint Voucher) payments</span></li>
                        <li><span>Additional service payments</span></li>
                        <li><span>Salary management</span></li>
                        <li><span>Asset management</span></li>
                        <li><span>Expense management</span></li>
                        <li><span>Financial statements (monthly, yearly, custom period)</span></li>
                        <li><span>Cash flow analysis</span></li>
                        <li><span>Outstanding dues tracking</span></li>
                        <li><span>Automatic balance reconciliation</span></li>
                    </ul>
                </div>
            </section>

            <!-- 6. Dashboards & Reporting -->
            <section class="feature-category hidden" data-category="dashboards">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">📊</div>
                        <h2>Dashboards & Reporting</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Static reports don't help owners make fast decisions. Managers need real-time insights into what's making or losing money.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete dashboard and reporting system with admin dashboard, multi-currency charts, profit breakdowns, outstanding dues, and exportable reports.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Admin Dashboard</h4>
                        <p>Comprehensive admin dashboard with all key metrics</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Multi-Currency Cash Flow</h4>
                        <p>Cash flow charts with multi-currency support</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📅</div>
                        <h4>Daily/Monthly/Yearly Filters</h4>
                        <p>Filter data by different time periods</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💎</div>
                        <h4>Profit Cards</h4>
                        <p>Profit cards showing today, month, and year performance</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔍</div>
                        <h4>Drill-Down Profit View</h4>
                        <p>Drill down from profit sources to individual items</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📄</div>
                        <h4>Outstanding Dues</h4>
                        <p>Track client pending payments and outstanding dues</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Admin Dashboard with comprehensive metrics</span></li>
                        <li><span>Multi-currency cash flow charts</span></li>
                        <li><span>Daily/monthly/yearly filters</span></li>
                        <li><span>Profit cards (today, month, year)</span></li>
                        <li><span>Drill-down profit source view</span></li>
                        <li><span>Item-level profit printing</span></li>
                        <li><span>Outstanding dues overview</span></li>
                        <li><span>Client pending payments tracking</span></li>
                        <li><span>Ticket booking periods overview</span></li>
                        <li><span>Today's departures tracking</span></li>
                        <li><span>User performance & sales tracking</span></li>
                        <li><span>Notifications on money in/out</span></li>
                        <li><span>Reports (airline sales, financial, branch-wise, user performance)</span></li>
                        <li><span>Exportable data (Excel/PDF)</span></li>
                    </ul>
                </div>
            </section>

            <!-- 7. Automation & Intelligence -->
            <section class="feature-category hidden" data-category="automation">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🤖</div>
                        <h2>Automation & Intelligence</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Manual communication and data entry waste hours every day. Staff spend time copying/pasting passenger data, sending repetitive emails, and following up manually.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete automation system with profit calculation, real-time analytics, interactive charts, email/WhatsApp automation, OCR auto-fill, and reminder system.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Automated Profit Calculation</h4>
                        <p>Automatic profit calculation for tickets, visas, hotels, and Umrah</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Real-Time Analytics</h4>
                        <p>Interactive dashboard with real-time data visualization</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📈</div>
                        <h4>Interactive Charts</h4>
                        <p>Visual charts for financial and operational data</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Source-Wise Profit Breakdown</h4>
                        <p>Detailed profit analysis by source</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📧</div>
                        <h4>Email Automation</h4>
                        <p>Automated emails for tickets, visas, hotels, Umrah, and invoices</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💬</div>
                        <h4>WhatsApp Automation</h4>
                        <p>Tenant-configurable WhatsApp messaging with templates</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Automated profit calculation (ticket, visa, hotel, Umrah)</span></li>
                        <li><span>Real-time analytics dashboard</span></li>
                        <li><span>Interactive financial charts</span></li>
                        <li><span>Source-wise profit breakdown</span></li>
                        <li><span>Automated email notifications (tickets, visas, hotels, Umrah, invoices)</span></li>
                        <li><span>Branded email templates</span></li>
                        <li><span>PDF attachments in emails</span></li>
                        <li><span>Email delivery logs</span></li>
                        <li><span>WhatsApp automation (tenant-configurable)</span></li>
                        <li><span>WhatsApp message templates</span></li>
                        <li><span>WhatsApp delivery status tracking</span></li>
                        <li><span>WhatsApp analytics</span></li>
                        <li><span>Reminder & to-do system</span></li>
                        <li><span>OCR auto-fill (tickets & passports)</span></li>
                    </ul>
                </div>
            </section>

            <!-- 8. Multi-Tenant & Multi-Branch System -->
            <section class="feature-category hidden" data-category="multibranch">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🏢</div>
                        <h2>Multi-Tenant & Multi-Branch System</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Agencies with multiple branches lack centralized control, duplicate data, inconsistent processes, and no way to compare branch performance.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Full SaaS multi-tenant architecture with multi-branch support, separate data per tenant, branch-level operations, and tenant Super Admin dashboard for performance comparison.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">🏢</div>
                        <h4>Full SaaS Multi-Tenant</h4>
                        <p>Complete multi-tenant architecture with data isolation</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🌐</div>
                        <h4>Multi-Branch Support</h4>
                        <p>Support multiple branches per tenant</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔒</div>
                        <h4>Separate Data Per Tenant</h4>
                        <p>Complete data isolation between tenants</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🏢</div>
                        <h4>Branch-Level Operations</h4>
                        <p>Manage operations at branch level</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Tenant Super Admin Dashboard</h4>
                        <p>Compare branch performance and export reports</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📧</div>
                        <h4>Shared SMTP & WhatsApp</h4>
                        <p>Shared communication channels with branch-specific branding</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Full SaaS multi-tenant architecture</span></li>
                        <li><span>Multi-branch support per tenant</span></li>
                        <li><span>Separate data per tenant</span></li>
                        <li><span>Branch-level operations</span></li>
                        <li><span>Tenant Super Admin dashboard</span></li>
                        <li><span>Compare branch performance</span></li>
                        <li><span>Export branch reports</span></li>
                        <li><span>Shared SMTP & WhatsApp with branch-specific branding</span></li>
                        <li><span>Branch name, address, phone appended automatically</span></li>
                    </ul>
                </div>
            </section>

            <!-- 8. Maktob Management -->
            <section class="feature-category hidden" data-category="maktob">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🧾</div>
                        <h2>Maktob (Official Letter) Management</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Travel agencies struggle with managing official letters, agreements, and correspondence. Manual tracking leads to lost documents, version confusion, and compliance issues.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete Maktob management system with numbering, multi-language support, PDF generation, and audit logging for all official correspondence.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">📝</div>
                        <h4>Issued & Received Letters</h4>
                        <p>Track both incoming and outgoing official correspondence</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔢</div>
                        <h4>Automatic Numbering</h4>
                        <p>Systematic Maktob numbering for easy reference and tracking</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🌐</div>
                        <h4>Multi-Language Support</h4>
                        <p>Create letters in English, Dari, and Pashto</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📄</div>
                        <h4>PDF Generation</h4>
                        <p>Generate professional PDF documents with preview capability</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📁</div>
                        <h4>Status Management</h4>
                        <p>Track letters through Draft, Sent, and Archived statuses</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🏢</div>
                        <h4>Branch-Aware Handling</h4>
                        <p>Manage letters with branch-specific context and access</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Issued and received official letters management</span></li>
                        <li><span>Automatic Maktob numbering system</span></li>
                        <li><span>Multi-language support (English, Dari, Pashto)</span></li>
                        <li><span>PDF generation and download functionality</span></li>
                        <li><span>PDF preview within the system</span></li>
                        <li><span>Draft/Sent/Archived status tracking</span></li>
                        <li><span>Branch-aware Maktob handling</span></li>
                        <li><span>Search, pagination, and filtering capabilities</span></li>
                        <li><span>Audit logging for all Maktob actions</span></li>
                    </ul>
                </div>
            </section>

            <!-- 9. HR & Attendance Management -->
            <section class="feature-category hidden" data-category="hr">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🕒</div>
                        <h2>HR & Attendance Management</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Tracking employee attendance, calculating payroll, and managing performance across multiple branches is time-consuming and error-prone.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Complete HR management system with attendance tracking, payroll integration, and performance reporting for all branches.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">📅</div>
                        <h4>Employee Attendance</h4>
                        <p>Track attendance per employee with check-in/check-out times</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🏢</div>
                        <h4>Branch-Level Tracking</h4>
                        <p>Manage attendance separately for each branch location</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💰</div>
                        <h4>Salary Integration</h4>
                        <p>Automatic salary calculation based on attendance and performance</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Performance Reporting</h4>
                        <p>Generate performance reports based on attendance and KPIs</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">👤</div>
                        <h4>Employee Records</h4>
                        <p>Complete employee profiles with attendance history</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📈</div>
                        <h4>Productivity Analytics</h4>
                        <p>Analyze attendance patterns and productivity trends</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Employee attendance tracking system</span></li>
                        <li><span>Branch-level attendance management</span></li>
                        <li><span>Integration with salary calculation module</span></li>
                        <li><span>Performance-based reporting</span></li>
                        <li><span>Complete employee records management</span></li>
                        <li><span>Attendance analytics and trends</span></li>
                        <li><span>Leave management and approvals</span></li>
                        <li><span>Overtime tracking and calculation</span></li>
                    </ul>
                </div>
            </section>

            <!-- 10. Communication & Collaboration -->
            <section class="feature-category hidden" data-category="communication">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">💬</div>
                        <h2>Communication & Collaboration</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Agencies need seamless communication between branches and with other agencies. Manual coordination leads to delays and miscommunication.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Built-in inter-tenant chat and collaboration tools for ticket/visa selling between agencies with controlled access.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">💬</div>
                        <h4>Inter-Tenant Chat</h4>
                        <p>Real-time messaging between different agencies</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🤝</div>
                        <h4>Business Collaboration</h4>
                        <p>Coordinate ticket, visa, and Umrah sales between agencies</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📄</div>
                        <h4>Shared Agreements</h4>
                        <p>Create and manage shared agreements with controlled access</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔒</div>
                        <h4>Controlled Access</h4>
                        <p>Manage permissions for shared documents and communications</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📊</div>
                        <h4>Collaboration Analytics</h4>
                        <p>Track collaboration activities and outcomes</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📱</div>
                        <h4>Mobile Collaboration</h4>
                        <p>Access collaboration tools from mobile devices</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Inter-tenant chat functionality</span></li>
                        <li><span>Tenant-to-tenant business collaboration</span></li>
                        <li><span>Ticket, visa, and Umrah selling between tenants</span></li>
                        <li><span>Shared agreements with controlled access</span></li>
                        <li><span>Real-time communication tools</span></li>
                        <li><span>Collaboration activity tracking</span></li>
                        <li><span>Mobile-friendly collaboration interface</span></li>
                        <li><span>Document sharing with access control</span></li>
                    </ul>
                </div>
            </section>

            <!-- 11. Security & Audit Logs -->
            <section class="feature-category hidden" data-category="security">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🔐</div>
                        <h2>Security & Audit Logs</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Financial systems need complete accountability. Staff changes, financial adjustments, and critical operations must be traceable for compliance and fraud investigation.</p>

                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Full auditability built into every transaction — track who changed what, when, and from where. Enterprise-grade security for sensitive data.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">📝</div>
                        <h4>Comprehensive Audit Logs</h4>
                        <p>Who changed what, when, and from where — full transaction history</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔒</div>
                        <h4>Role-Based Security</h4>
                        <p>Secure actions only allowed by appropriate roles</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📋</div>
                        <h4>Change History</h4>
                        <p>Complete history of sensitive operations for compliance</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🛡️</div>
                        <h4>Data Encryption</h4>
                        <p>Military-grade AES-256 encryption for all data</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💾</div>
                        <h4>Automated Backups</h4>
                        <p>Daily backups with point-in-time recovery capability</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📄</div>
                        <h4>Compliance Reports</h4>
                        <p>Generate compliance reports for audits automatically</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Audit log (who changed what & when)</span></li>
                        <li><span>Secure role-based actions</span></li>
                        <li><span>Change history for sensitive operations</span></li>
                        <li><span>Bank-level data encryption</span></li>
                        <li><span>Automated daily backups</span></li>
                        <li><span>Point-in-time recovery</span></li>
                        <li><span>Compliance reports for audits</span></li>
                        <li><span>GDPR-compliant data handling</span></li>
                        <li><span>Tenant isolation and data protection</span></li>
                        <li><span>Secure document handling and storage</span></li>
                    </ul>
                </div>
            </section>

            <!-- 9. Client Portal -->
            <section class="feature-category hidden" data-category="portal">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">👥</div>
                        <h2>Client Portal</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">Clients constantly ask for booking status, ticket copies, and invoices. Staff waste time on repetitive requests that should be self-service.</p>
                        
                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">A dedicated client portal where customers access their booking history, download documents, and get real-time status updates 24/7.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">🔐</div>
                        <h4>Client Login Access</h4>
                        <p>Secure client login to view their accounts</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🎫</div>
                        <h4>Ticket & Service History</h4>
                        <p>Complete history of all bookings and services</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">💳</div>
                        <h4>Balance & Transactions</h4>
                        <p>View account balance and transaction history</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📥</div>
                        <h4>Download Documents</h4>
                        <p>Download invoices, tickets, and documents anytime</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📱</div>
                        <h4>Mobile Friendly</h4>
                        <p>Fully responsive design for all devices</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔔</div>
                        <h4>Real-Time Notifications</h4>
                        <p>Clients notified of booking updates automatically</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Client login access with secure authentication</span></li>
                        <li><span>Ticket & service history view</span></li>
                        <li><span>Balance & transaction viewing</span></li>
                        <li><span>Downloadable invoices & documents</span></li>
                        <li><span>Real-time booking status updates</span></li>
                        <li><span>Mobile app access (iOS & Android)</span></li>
                        <li><span>Push notifications for important updates</span></li>
                        <li><span>Offline document access</span></li>
                    </ul>
                </div>
            </section>

            <!-- 10. Onboarding & Learning -->
            <section class="feature-category hidden" data-category="learning">
                <div class="category-header">
                    <div class="category-title">
                        <div class="category-icon">🎓</div>
                        <h2>Onboarding & Learning System</h2>
                    </div>

                    <div class="problem-solution">
                        <h3>The Problem</h3>
                        <p class="problem-text">New users struggle to adopt complex systems. Teams need quick access to tutorials and guides to use features effectively.</p>
                        
                        <h3 class="solution-heading">Our Solution</h3>
                        <p class="solution-text">Built-in learning system with Vimeo-hosted tutorials, step-by-step guides, role-based learning paths, and feature-specific help videos.</p>
                    </div>
                </div>

                <div class="capabilities-grid">
                    <div class="capability-card">
                        <div class="capability-icon">🎥</div>
                        <h4>Vimeo-Hosted Tutorials</h4>
                        <p>Fast, reliable video hosting for training content</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">📚</div>
                        <h4>Step-by-Step Guides</h4>
                        <p>Written guides for every feature and workflow</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🎯</div>
                        <h4>Role-Based Learning Paths</h4>
                        <p>Customized learning for each role (admin, sales, finance)</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🔍</div>
                        <h4>Feature-Specific Help</h4>
                        <p>Contextual help available right in the feature</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">⭐</div>
                        <h4>Quick Wins Series</h4>
                        <p>Short videos on getting started and common tasks</p>
                    </div>
                    <div class="capability-card">
                        <div class="capability-icon">🤝</div>
                        <h4>Support Ticket System</h4>
                        <p>Lightweight built-in support for issues and questions</p>
                    </div>
                </div>

                <div class="features-list">
                    <h4>Key Capabilities Include:</h4>
                    <ul>
                        <li><span>Vimeo-hosted tutorials for fast loading</span></li>
                        <li><span>Step-by-step guides for all features</span></li>
                        <li><span>Role-based learning paths</span></li>
                        <li><span>Feature-specific help videos</span></li>
                        <li><span>Quick wins & getting started series</span></li>
                        <li><span>Built-in support ticket system</span></li>
                        <li><span>Issue categorization & SLA tracking</span></li>
                        <li><span>Screenshot upload & ticket history</span></li>
                    </ul>
                </div>
            </section>

            <!-- ROI Section -->
            <section class="roi-section">
                <h3>Measurable Business Impact</h3>
                <div class="roi-grid">
                    <div class="roi-item">
                        <div class="roi-number">80%</div>
                        <div class="roi-label">Reduction in manual work & data entry errors</div>
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

            <!-- Roles Section -->
            <section class="roles-section">
                <h3>Roles & Permissions</h3>
                <p style="text-align: center; color: #666; margin-bottom: 2rem;">Clear, role-based access aligned with real agency operations</p>
                <div class="roles-grid">
                    <div class="role-card">
                        <div class="role-icon">👔</div>
                        <h4>Tenant Super Admin</h4>
                        <p>Agency Owner<br><span style="font-size: 0.85rem;">Global dashboard, branch comparison, export reports</span></p>
                    </div>
                    <div class="role-card">
                        <div class="role-icon">⚙️</div>
                        <h4>Admin</h4>
                        <p>System Management<br><span style="font-size: 0.85rem;">User management, settings, audit logs</span></p>
                    </div>
                    <div class="role-card">
                        <div class="role-icon">💼</div>
                        <h4>Finance</h4>
                        <p>Accounting<br><span style="font-size: 0.85rem;">Ledgers, invoices, payments, reports</span></p>
                    </div>
                    <div class="role-card">
                        <div class="role-icon">🎯</div>
                        <h4>Sales</h4>
                        <p>Booking Team<br><span style="font-size: 0.85rem;">Bookings, clients, availability, quotes</span></p>
                    </div>
                    <div class="role-card">
                        <div class="role-icon">🕋</div>
                        <h4>Umrah Team</h4>
                        <p>Pilgrimage Ops<br><span style="font-size: 0.85rem;">Family bookings, members, payments, visas</span></p>
                    </div>
                </div>
                <p style="text-align: center; color: #666; margin-top: 2rem; font-style: italic;">Each role only sees and edits what they are allowed to</p>
            </section>

            <!-- Architecture Section -->
            <section class="architecture-section">
                <h3>Platform & Infrastructure</h3>
                <p style="text-align: center; color: #666; margin-bottom: 2rem;">Designed for scale, automation, and growth</p>
                <div class="architecture-grid">
                    <div class="arch-item">
                        <h4>SaaS-Ready Architecture</h4>
                        <p>Multi-tenant platform built on modern cloud infrastructure with automatic scaling</p>
                    </div>
                    <div class="arch-item">
                        <h4>Multi-Tenant Support</h4>
                        <p>Complete data isolation between tenants with shared infrastructure efficiency</p>
                    </div>
                    <div class="arch-item">
                        <h4>Investor-Ready Structure</h4>
                        <p>Built with growth and exit in mind — scalable, profitable, and expandable</p>
                    </div>
                    <div class="arch-item">
                        <h4>Future-Ready for AI & Integrations</h4>
                        <p>Architecture designed for easy integration of AI, third-party APIs, and new features</p>
                    </div>
                    <div class="arch-item">
                        <h4>API-First Design</h4>
                        <p>Complete REST API for custom integrations and third-party connections</p>
                    </div>
                    <div class="arch-item">
                        <h4>Performance Optimized</h4>
                        <p>Sub-second response times with caching, CDN, and database optimization</p>
                    </div>
                </div>
            </section>

            <!-- Investor Tagline -->
            <section class="investor-tagline">
                ✅ An all-in-one, automation-first, multi-branch travel agency SaaS covering ticketing, Umrah, visas, hotels, finance, communication, reporting, and client management — built for real agency operations, not theory.
            </section>

            <!-- CTA -->
            <section class="advanced-cta">
                <h3>Ready to Revolutionize Your Travel Agency?</h3>
                <p>Start your free 14-day trial today. No credit card required. Full access to all features.</p>
                <div class="cta-buttons">
                    <a href="book-demo.php" class="btn-primaryi">Schedule Demo</a>
                    <a href="index.php#pricing" class="btn-secondaryi">View Pricing</a>
                </div>
            </section>

        </div>
    </div>

    <script>
        // Feature Navigation
        document.querySelectorAll('.nav-tab').forEach(btn => {
            btn.addEventListener('click', function() {
                const category = this.dataset.category;

                // Update active button
                document.querySelectorAll('.nav-tab').forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                // Update visible categories
                document.querySelectorAll('.feature-category').forEach(cat => {
                    if (cat.dataset.category === category) {
                        cat.classList.remove('hidden');
                    } else {
                        cat.classList.add('hidden');
                    }
                });

                // Scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
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
