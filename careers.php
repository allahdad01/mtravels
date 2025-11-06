<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/conn.php';
require_once 'includes/cache.php';

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
        error_log("Error fetching platform settings: " . $e->getMessage());
        return [];
    }
}

// Helper function to get setting value
function getSetting($settings, $key, $default = '') {
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}

// Fetch platform settings
$platform_settings = getPlatformSettings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Careers - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Join the MTravels team and help revolutionize travel agency management. Explore exciting career opportunities in technology and travel.">
        <!-- Favicon -->
        <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #4099ff;
            --primary-dark: #2673cc;
            --primary-light: #a0e6ff;
            --secondary: #2ed8b6;
            --secondary-dark: #24a88f;
            --secondary-light: #8ef0e0;
            --accent: #25c6b4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #0f172a;
            --light: #f8fafc;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            line-height: 1.6;
            color: var(--gray-800);
            background: var(--white);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Advanced Navbar */
        .navbar {
            position: fixed;
            top: 30px;
            left: 100px;
            right: 100px;
            padding: 1.5rem 2rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 2rem;
            border-radius: 50px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2);
        }

        .nav-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .logo img {
            max-height: 40px;
            width: auto;
        }

        .logo-text {
            font-size: 1.8rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--primary);
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 600;
            position: relative;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
            color: #ffffff !important;
            border-bottom: none !important;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.4);
        }

        /* Hero Section */
        .hero {
            padding: 12rem 0 8rem;
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            text-align: center;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 1.5rem;
            line-height: 1.1;
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.9;
        }

        /* Culture Section */
        .culture {
            padding: 6rem 0;
            background: var(--white);
        }

        .culture-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .culture-item {
            text-align: center;
            padding: 2rem;
            background: var(--gray-50);
            border-radius: 20px;
            transition: transform 0.3s ease;
        }

        .culture-item:hover {
            transform: translateY(-5px);
        }

        .culture-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .culture-item h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .culture-item p {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* Jobs Section */
        .jobs {
            padding: 6rem 0;
            background: var(--gray-50);
        }

        .jobs-grid {
            display: grid;
            gap: 2rem;
        }

        .job-card {
            background: var(--white);
            border-radius: 20px;
            padding: 3rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(64, 153, 255, 0.1);
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .job-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .job-location {
            color: var(--primary);
            font-weight: 600;
        }

        .job-type {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .job-description {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .job-requirements {
            margin-bottom: 2rem;
        }

        .job-requirements h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .job-requirements ul {
            list-style: none;
            padding: 0;
        }

        .job-requirements li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
            color: var(--gray-600);
        }

        .job-requirements li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
        }

        .apply-btn {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .apply-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.3);
        }

        /* Benefits Section */
        .benefits {
            padding: 6rem 0;
            background: var(--white);
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
        }

        .benefit-item {
            text-align: center;
            padding: 2rem;
        }

        .benefit-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .benefit-item h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .benefit-item p {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* CTA Section */
        .cta {
            padding: 6rem 0;
            background: var(--gray-50);
            text-align: center;
        }

        .cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Footer */
        .footer {
            background: var(--gray-50);
            color: var(--gray-900);
            padding: 4rem 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 2rem;
        }

        .footer-section h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--gray-900);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section li {
            margin-bottom: 0.8rem;
        }

        .footer-section a {
            color: var(--gray-600);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            padding-top: 2rem;
            border-top: 1px solid var(--primary);
            text-align: center;
            color: var(--gray-600);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                left: 20px;
                right: 20px;
                padding: 1rem 1.5rem;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .culture-grid {
                grid-template-columns: 1fr;
            }

            .jobs-grid {
                gap: 1.5rem;
            }

            .job-card {
                padding: 2rem;
            }

            .job-header {
                flex-direction: column;
                gap: 1rem;
            }

            .benefits-grid {
                grid-template-columns: 1fr;
            }

            .cta h2 {
                font-size: 2rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="index.php" class="logo">
                    <img src="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'logo.png') ?>" alt="Logo" style="height: 40px;">
                    <span class="logo-text"><?= htmlspecialchars(getSetting($platform_settings, 'platform_name') ?? 'MTravels') ?></span>
                </a>
                <div class="nav-menu">
                    <ul class="nav-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#pricing">Pricing</a></li>
                        <li><a href="about.php">About</a></li>
                        <li><a href="careers.php">Careers</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                    </ul>
                    <div class="nav-actions">
                        <a href="login.php" class="nav-login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.3s;">Login</a>
                        <a href="book-demo.php" class="btn btn-primary">
                            <span>Book a Demo</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Join Our Team</h1>
            <p>Help us revolutionize travel agency management. We're looking for passionate individuals ready to make an impact in the travel technology industry.</p>
        </div>
    </section>

    <!-- Culture Section -->
    <section class="culture">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 4rem;">
                <h2>Why Work With Us?</h2>
                <p>Discover what makes <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?> a great place to grow your career</p>
            </div>
            <div class="culture-grid">
                <div class="culture-item">
                    <div class="culture-icon">🚀</div>
                    <h3>Innovation First</h3>
                    <p>Work on cutting-edge technology that transforms how travel agencies operate worldwide.</p>
                </div>
                <div class="culture-item">
                    <div class="culture-icon">🌍</div>
                    <h3>Global Impact</h3>
                    <p>Your work directly impacts thousands of travel agencies and millions of travelers globally.</p>
                </div>
                <div class="culture-item">
                    <div class="culture-icon">📈</div>
                    <h3>Growth Opportunities</h3>
                    <p>Continuous learning and career development in a fast-growing industry.</p>
                </div>
                <div class="culture-item">
                    <div class="culture-icon">🤝</div>
                    <h3>Collaborative Culture</h3>
                    <p>Work with talented professionals in a supportive and inclusive environment.</p>
                </div>
                <div class="culture-item">
                    <div class="culture-icon">⚡</div>
                    <h3>Work-Life Balance</h3>
                    <p>Flexible work arrangements and a healthy balance between professional and personal life.</p>
                </div>
                <div class="culture-item">
                    <div class="culture-icon">🎯</div>
                    <h3>Meaningful Work</h3>
                    <p>Contribute to solutions that make travel businesses more efficient and customer-focused.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Jobs Section -->
    <section class="jobs">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 4rem;">
                <h2>Open Positions</h2>
                <p>Join our growing team and help shape the future of travel technology</p>
            </div>
            <div class="jobs-grid">
                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <h3 class="job-title">Senior PHP Developer</h3>
                            <div class="job-location">Kabul, Afghanistan (Remote Options)</div>
                        </div>
                        <div class="job-type">Full-time</div>
                    </div>
                    <p class="job-description">We're looking for an experienced PHP developer to join our backend team. You'll work on complex travel management systems, API development, and database optimization.</p>
                    <div class="job-requirements">
                        <h4>Requirements:</h4>
                        <ul>
                            <li>5+ years of PHP development experience</li>
                            <li>Strong knowledge of Laravel framework</li>
                            <li>Experience with MySQL and database optimization</li>
                            <li>Familiarity with RESTful APIs and microservices</li>
                            <li>Knowledge of modern frontend technologies (JavaScript, React)</li>
                        </ul>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>

                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <h3 class="job-title">Frontend Developer</h3>
                            <div class="job-location">Kabul, Afghanistan (Remote Options)</div>
                        </div>
                        <div class="job-type">Full-time</div>
                    </div>
                    <p class="job-description">Join our frontend team to create beautiful, responsive user interfaces for our travel management platform. You'll work with modern JavaScript frameworks and cutting-edge CSS techniques.</p>
                    <div class="job-requirements">
                        <h4>Requirements:</h4>
                        <ul>
                            <li>3+ years of frontend development experience</li>
                            <li>Proficiency in JavaScript (ES6+), HTML5, and CSS3</li>
                            <li>Experience with React.js or Vue.js</li>
                            <li>Knowledge of state management (Redux, Vuex)</li>
                            <li>Understanding of responsive design principles</li>
                        </ul>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>

                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <h3 class="job-title">DevOps Engineer</h3>
                            <div class="job-location">Kabul, Afghanistan (Remote Options)</div>
                        </div>
                        <div class="job-type">Full-time</div>
                    </div>
                    <p class="job-description">Help us build and maintain scalable infrastructure for our growing platform. You'll work with cloud services, CI/CD pipelines, and automation tools.</p>
                    <div class="job-requirements">
                        <h4>Requirements:</h4>
                        <ul>
                            <li>3+ years of DevOps or infrastructure experience</li>
                            <li>Experience with AWS or similar cloud platforms</li>
                            <li>Knowledge of Docker and containerization</li>
                            <li>Familiarity with CI/CD tools (Jenkins, GitLab CI)</li>
                            <li>Understanding of monitoring and logging tools</li>
                        </ul>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>

                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <h3 class="job-title">Product Manager</h3>
                            <div class="job-location">Kabul, Afghanistan (Remote Options)</div>
                        </div>
                        <div class="job-type">Full-time</div>
                    </div>
                    <p class="job-description">Drive product strategy and roadmap for our travel management platform. You'll work closely with engineering, design, and business teams to deliver exceptional products.</p>
                    <div class="job-requirements">
                        <h4>Requirements:</h4>
                        <ul>
                            <li>3+ years of product management experience</li>
                            <li>Experience in SaaS or B2B software products</li>
                            <li>Strong analytical and problem-solving skills</li>
                            <li>Excellent communication and leadership abilities</li>
                            <li>Understanding of agile development methodologies</li>
                        </ul>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>

                <div class="job-card">
                    <div class="job-header">
                        <div>
                            <h3 class="job-title">Customer Success Manager</h3>
                            <div class="job-location">Kabul, Afghanistan (Remote Options)</div>
                        </div>
                        <div class="job-type">Full-time</div>
                    </div>
                    <p class="job-description">Ensure our clients achieve maximum value from our platform. You'll work directly with travel agencies to understand their needs and drive successful outcomes.</p>
                    <div class="job-requirements">
                        <h4>Requirements:</h4>
                        <ul>
                            <li>2+ years of customer success or account management experience</li>
                            <li>Experience in SaaS or software industry preferred</li>
                            <li>Strong interpersonal and communication skills</li>
                            <li>Ability to understand technical concepts and explain them clearly</li>
                            <li>Problem-solving mindset with a customer-first approach</li>
                        </ul>
                    </div>
                    <button class="apply-btn">Apply Now</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 4rem;">
                <h2>Benefits & Perks</h2>
                <p>We offer competitive benefits to support your professional and personal growth</p>
            </div>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="benefit-icon">💼</div>
                    <h3>Competitive Salary</h3>
                    <p>Market-leading compensation with performance-based bonuses and equity options.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">🏥</div>
                    <h3>Health Insurance</h3>
                    <p>Comprehensive health, dental, and vision coverage for you and your family.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">🏖️</div>
                    <h3>Paid Time Off</h3>
                    <p>Generous vacation policy plus paid holidays and sick leave.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">📚</div>
                    <h3>Learning Budget</h3>
                    <p>Dedicated budget for conferences, courses, and professional development.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">🏠</div>
                    <h3>Remote Work</h3>
                    <p>Flexible work arrangements with remote work options available.</p>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon">☕</div>
                    <h3>Great Culture</h3>
                    <p>Collaborative environment with team events and social activities.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Join Our Mission?</h2>
            <p>Don't see a position that matches your skills? We're always interested in hearing from talented individuals who share our passion for innovation.</p>
            <div class="cta-buttons">
                <a href="mailto:careers@mtravels.com" class="btn btn-primary">Send Us Your Resume</a>
                <a href="index.php#contact" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">Contact Us</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></h3>
                    <p style="color: var(--gray-300); line-height: 1.6;">
                        <?php echo getSetting($platform_settings, 'platform_description', 'Professional travel agency management platform providing comprehensive solutions for booking management, financial operations, customer service, and business intelligence.'); ?>
                    </p>
                </div>
                <div class="footer-section">
                    <h3>Product</h3>
                    <ul>
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#pricing">Pricing</a></li>
                        <li><a href="integrations.php">Integrations</a></li>
                        <li><a href="api-docs.php">API Documentation</a></li>
                        <li><a href="security.php">Security</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="careers.php">Careers</a></li>
                        <li><a href="press.php">Press</a></li>
                        <li><a href="blog.php">Blog</a></li>
                        <li><a href="partners.php">Partners</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="help.php">Help Center</a></li>
                        <li><a href="index.php#contact">Contact Support</a></li>
                        <li><a href="status.php">System Status</a></li>
                        <li><a href="community.php">Community</a></li>
                        <li><a href="training.php">Training</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#' && href.length > 1) {
                    e.preventDefault();
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });

        // Apply button functionality
        document.querySelectorAll('.apply-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                alert('Application form coming soon! Please send your resume to careers@mtravels.com');
            });
        });
    </script>
</body>
</html>