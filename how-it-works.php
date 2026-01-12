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

        .how-it-works-wrapper {
            min-height: 100vh;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Hero Section */
        .hiw-hero {
            position: relative;
            padding: 8rem 2rem 5rem 2rem;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            overflow: hidden;
            text-align: center;
            margin-top: 120px;
            z-index: 1;
        }

        .hiw-hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
        }

        .hiw-hero::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            bottom: -150px;
            left: -150px;
        }

        .hiw-hero-content {
            position: relative;
            z-index: 1;
            max-width: 900px;
            margin: 0 auto;
        }

        .hiw-hero h1 {
            font-size: 3.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.2;
        }

        .hiw-hero p {
            font-size: 1.2rem;
            opacity: 0.95;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hiw-hero-tagline {
            background: rgba(255, 255, 255, 0.15);
            padding: 1.5rem;
            border-radius: 12px;
            font-size: 1rem;
            line-height: 1.7;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Steps Container */
        .hiw-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 4rem 2rem;
        }

        /* Step Card */
        .step-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 5rem;
            align-items: center;
            animation: fadeInUp 0.6s ease-out;
        }

        .step-card:nth-child(even) {
            direction: rtl;
        }

        .step-card:nth-child(even) > * {
            direction: ltr;
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

        .step-number {
            display: inline-block;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .step-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .step-subtitle {
            font-size: 1rem;
            color: #4099ff;
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .step-content {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .step-section {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #4099ff;
            transition: all 0.3s ease;
        }

        .step-section h4 {
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 0.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .step-section ul {
            list-style: none;
            padding: 0;
        }

        .step-section li {
            padding: 0.5rem 0;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .step-section li::before {
            content: '✓';
            color: #10b981;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .step-result {
            background: linear-gradient(135deg, #10b98115, #4099ff15);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #10b981;
            margin-top: 1rem;
        }

        .step-result h5 {
            color: #10b981;
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .step-result p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        .step-problem {
            background: linear-gradient(135deg, #ef444415, #ef444408);
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 4px solid #ef4444;
            margin-top: 1rem;
        }

        .step-problem h5 {
            color: #ef4444;
            font-size: 0.9rem;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: 0.5px;
        }

        .step-problem p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin: 0;
        }

        .step-visual {
            position: relative;
        }

        .step-visual-box {
            background: var(--bg-secondary);
            padding: 3rem 2rem;
            border-radius: 16px;
            border: 2px solid var(--border);
            text-align: center;
            transition: all 0.3s ease;
        }

        .step-visual-box:hover {
            border-color: #4099ff;
            box-shadow: 0 10px 30px rgba(64, 153, 255, 0.15);
        }

        .step-visual-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }

        .step-visual-text {
            font-size: 1.1rem;
            color: var(--text-primary);
            font-weight: 600;
            line-height: 1.6;
        }

        /* Growth Section */
        .growth-section {
            background: var(--bg-surface);
            padding: 3rem 2rem;
            border-radius: 16px;
            margin-bottom: 4rem;
            transition: all 0.3s ease;
        }

        html.dark-mode .growth-section {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .growth-section h3 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 2rem;
            text-align: center;
        }

        .growth-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .growth-item {
            text-align: center;
            padding: 2rem;
            background: var(--bg-secondary);
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .growth-item:hover {
            transform: translateY(-8px);
            border-color: #4099ff;
        }

        .growth-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .growth-item h4 {
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .growth-item p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Summary Section */
        .summary-section {
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
            color: white;
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 4rem;
        }

        .summary-section h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            opacity: 0.95;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
        }

        .summary-tagline {
            font-size: 2rem;
            line-height: 1.5;
            margin: 0;
            font-weight: 700;
        }

        /* CTA Section */
        .hiw-cta {
            background: var(--bg-surface);
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 4rem;
        }

        .hiw-cta h3 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .hiw-cta p {
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

        

        /* Responsive */
        @media (max-width: 768px) {
            .hiw-hero {
                margin-top: 80px;
            }

            .hiw-hero h1 {
                font-size: 2.2rem;
            }

            .step-card {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .step-card:nth-child(even) {
                direction: ltr;
            }

            .step-title {
                font-size: 1.5rem;
            }

            .summary-tagline {
                font-size: 1.3rem;
            }

            .growth-grid {
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
        ['href' => 'index.php#pricing', 'label' => 'Pricing'],
        ['href' => 'index.php#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php'; 
    ?>

    <div class="how-it-works-wrapper">
        <!-- Hero -->
        <section class="hiw-hero">
            <div class="hiw-hero-content">
                <h1>Run Your Travel Agency in 5 Simple Steps</h1>
                <p>Start selling the same day, without technical knowledge</p>
                <div class="hiw-hero-tagline">
                    Our system is designed so any travel agency — small or multi-branch — can start working immediately, managing tickets, Umrah, visas, hotels, finance, and automation from one unified platform.
                </div>
            </div>
        </section>

        <!-- Steps -->
        <div class="hiw-container">

            <!-- Step 1 -->
            <div class="step-card">
                <div>
                    <div class="step-number">1️⃣</div>
                    <h2 class="step-title">Create Your Agency</h2>
                    <p class="step-subtitle">Get started in minutes</p>
                    
                    <div class="step-content">
                        <div class="step-section">
                            <h4>🎯 What You Do</h4>
                            <ul>
                                <li>Register your travel agency</li>
                                <li>Set agency name, logo, currency preferences</li>
                                <li>Configure email (SMTP) and WhatsApp automation once</li>
                                <li>Define financial base currencies (AFN, USD, AED, EUR)</li>
                            </ul>
                        </div>

                        <div class="step-result">
                            <h5>✓ Result</h5>
                            <p>Your agency system is ready instantly — no complex setup, no IT dependency.</p>
                        </div>

                        <div class="step-problem">
                            <h5>⚠️ Problem Solved</h5>
                            <p>No more waiting weeks for IT setup or needing technical staff to get started.</p>
                        </div>
                    </div>
                </div>

                <div class="step-visual">
                    <div class="step-visual-box">
                        <div class="step-visual-icon">⚙️</div>
                        <div class="step-visual-text">Setup takes minutes, not days</div>
                    </div>
                </div>
            </div>

            <!-- Step 2 -->
            <div class="step-card">
                <div>
                    <div class="step-number">2️⃣</div>
                    <h2 class="step-title">Add Branches & Users</h2>
                    <p class="step-subtitle">Scale without losing control</p>
                    
                    <div class="step-content">
                        <div class="step-section">
                            <h4>🏢 What You Do</h4>
                            <ul>
                                <li>Create unlimited branches</li>
                                <li>Each branch works independently</li>
                                <li>Add users with roles: Admin, Finance, Sales, Umrah</li>
                                <li>Branch name & address auto-appear on emails, WhatsApp, invoices, PDFs</li>
                            </ul>
                        </div>

                        <div class="step-result">
                            <h5>✓ Result</h5>
                            <p>Centralized control meets branch autonomy. Manage growth without chaos.</p>
                        </div>

                        <div class="step-problem">
                            <h5>⚠️ Problem Solved</h5>
                            <p>No more multi-branch chaos, duplicate data, or poor visibility across offices.</p>
                        </div>
                    </div>
                </div>

                <div class="step-visual">
                    <div class="step-visual-box">
                        <div class="step-visual-icon">🏢</div>
                        <div class="step-visual-text">Multi-branch ready out of the box</div>
                    </div>
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step-card">
                <div>
                    <div class="step-number">3️⃣</div>
                    <h2 class="step-title">Start Selling (Operations Begin)</h2>
                    <p class="step-subtitle">One system for everything you sell</p>
                    
                    <div class="step-content">
                        <div class="step-section">
                            <h4>✈️ Tickets & Reservations</h4>
                            <ul>
                                <li>Ticket booking with multi-currency pricing</li>
                                <li>Refunds & date changes tracking</li>
                                <li>Outstanding dues tracking</li>
                                <li>OCR auto-fill from ticket PDFs</li>
                            </ul>
                        </div>

                        <div class="step-section">
                            <h4>🕋 Umrah & Family Management</h4>
                            <ul>
                                <li>Family-based bookings with member tracking</li>
                                <li>Member-wise payments & family totals</li>
                                <li>Bank receipt tracking</li>
                                <li>Passport OCR for faster data entry</li>
                            </ul>
                        </div>

                        <div class="step-section">
                            <h4>🛂 Visa & 🏨 Hotel</h4>
                            <ul>
                                <li>Visa application & cancellation management</li>
                                <li>Hotel bookings & vouchers</li>
                                <li>Automated client notifications</li>
                                <li>Financial impact tracking</li>
                            </ul>
                        </div>

                        <div class="step-result">
                            <h5>✓ Result</h5>
                            <p>Faster selling, fewer errors, less manual work — your team is productive immediately.</p>
                        </div>

                        <div class="step-problem">
                            <h5>⚠️ Problem Solved</h5>
                            <p>No more disconnected systems, slow data entry, or manual follow-ups eating your time.</p>
                        </div>
                    </div>
                </div>

                <div class="step-visual">
                    <div class="step-visual-box">
                        <div class="step-visual-icon">🎯</div>
                        <div class="step-visual-text">Sell everything from one dashboard</div>
                    </div>
                </div>
            </div>

            <!-- Step 4 -->
            <div class="step-card">
                <div>
                    <div class="step-number">4️⃣</div>
                    <h2 class="step-title">System Automates Everything</h2>
                    <p class="step-subtitle">This is where the magic happens</p>
                    
                    <div class="step-content">
                        <div class="step-section">
                            <h4>💰 Finance & Accounting</h4>
                            <ul>
                                <li>Multi-currency cash flow tracking</li>
                                <li>Profit calculation (daily, monthly, yearly)</li>
                                <li>Supplier & client reconciliation</li>
                                <li>Outstanding dues tracking with reminders</li>
                                <li>Main account tracking (safe, bank, bourse)</li>
                            </ul>
                        </div>

                        <div class="step-section">
                            <h4>🤖 Communication Automation</h4>
                            <ul>
                                <li>Branded emails with PDF attachments</li>
                                <li>WhatsApp: tickets, invoices, reminders, updates</li>
                                <li>Email & WhatsApp delivery logs</li>
                                <li>To-do reminders for staff follow-ups</li>
                                <li>No manual sending — automatic based on events</li>
                            </ul>
                        </div>

                        <div class="step-section">
                            <h4>🔐 Security & Control</h4>
                            <ul>
                                <li>Audit logs (who changed what & when)</li>
                                <li>Role-based access control</li>
                                <li>Branch-level data isolation</li>
                                <li>Activity notifications & alerts</li>
                                <li>Bank-level encryption</li>
                            </ul>
                        </div>

                        <div class="step-result">
                            <h5>✓ Result</h5>
                            <p>You focus on business — finance, emails, payments, and follow-ups happen automatically.</p>
                        </div>

                        <div class="step-problem">
                            <h5>⚠️ Problem Solved</h5>
                            <p>No more manual follow-ups, missed payments, human mistakes, or lost communication logs.</p>
                        </div>
                    </div>
                </div>

                <div class="step-visual">
                    <div class="step-visual-box">
                        <div class="step-visual-icon">⚡</div>
                        <div class="step-visual-text">Automation handles the heavy lifting</div>
                    </div>
                </div>
            </div>

            <!-- Step 5 -->
            <div class="step-card">
                <div>
                    <div class="step-number">5️⃣</div>
                    <h2 class="step-title">Track Profit & Performance</h2>
                    <p class="step-subtitle">Make decisions using real data</p>
                    
                    <div class="step-content">
                        <div class="step-section">
                            <h4>📊 Interactive Dashboards</h4>
                            <ul>
                                <li>Cash flow by currency & period</li>
                                <li>Profit breakdown by source</li>
                                <li>Outstanding dues overview</li>
                                <li>Today's departures & bookings</li>
                                <li>Real-time notifications</li>
                            </ul>
                        </div>

                        <div class="step-section">
                            <h4>👥 Staff Performance</h4>
                            <ul>
                                <li>Sales comparison & rankings</li>
                                <li>Profit contribution per user</li>
                                <li>Branch performance analysis</li>
                                <li>Performance trends & forecasting</li>
                            </ul>
                        </div>

                        <div class="step-section">
                            <h4>📈 Advanced Reports</h4>
                            <ul>
                                <li>Airline sales reports with drill-down</li>
                                <li>Client statements & invoices</li>
                                <li>Branch comparison reports</li>
                                <li>Export to PDF / Excel — print-ready layouts</li>
                            </ul>
                        </div>

                        <div class="step-result">
                            <h5>✓ Result</h5>
                            <p>Full business visibility. Make fast decisions based on real data, not gut feeling.</p>
                        </div>

                        <div class="step-problem">
                            <h5>⚠️ Problem Solved</h5>
                            <p>No more running blind. See exactly where profit comes from and where it leaks.</p>
                        </div>
                    </div>
                </div>

                <div class="step-visual">
                    <div class="step-visual-box">
                        <div class="step-visual-icon">📊</div>
                        <div class="step-visual-text">Real-time insights for smarter decisions</div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Growth Section -->
        <div class="hiw-container">
            <div class="growth-section">
                <h3>🚀 Grow Without Limits</h3>
                <div class="growth-grid">
                    <div class="growth-item">
                        <div class="growth-icon">🏢</div>
                        <h4>Multi-Branch Ready</h4>
                        <p>Scale from 1 to 100+ branches without changing systems</p>
                    </div>
                    <div class="growth-item">
                        <div class="growth-icon">🤝</div>
                        <h4>Franchise-Friendly</h4>
                        <p>Built to support franchise structures and multi-owner models</p>
                    </div>
                    <div class="growth-item">
                        <div class="growth-icon">👥</div>
                        <h4>Client Portal</h4>
                        <p>Clients access their bookings 24/7 — reduce support calls</p>
                    </div>
                    <div class="growth-item">
                        <div class="growth-icon">🎫</div>
                        <h4>Support System</h4>
                        <p>Built-in support tickets with SLA tracking & categorization</p>
                    </div>
                    <div class="growth-item">
                        <div class="growth-icon">🎓</div>
                        <h4>Learning Built-In</h4>
                        <p>Tutorials, guides, & video onboarding included</p>
                    </div>
                    <div class="growth-item">
                        <div class="growth-icon">📈</div>
                        <h4>API & Integrations</h4>
                        <p>Connect with 3rd-party tools — extensible architecture</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Section -->
        <div class="hiw-container">
            <div class="summary-section">
                <h3>Your Journey in One Line</h3>
                <p class="summary-tagline">
                    Sell faster, automate operations, control finance, and scale your travel agency — all from one powerful system.
                </p>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="hiw-container">
            <div class="hiw-cta">
                <h3>Ready to Transform Your Travel Agency?</h3>
                <p>Start today and see why thousands of travel agencies trust MTravels for their operations.</p>
                <div class="cta-buttons">
                    <a href="book-demo.php" class="btn-primary">Schedule Your Demo</a>
                    <a href="features.php" class="btn-secondary">Explore All Features</a>
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
