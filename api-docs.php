<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
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
    <title>API Documentation - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Comprehensive API documentation for MTravels platform. Learn how to integrate with our REST API for travel agency management.">
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

        /* API Documentation Content */
        .api-content {
            padding: 6rem 0;
            background: var(--white);
        }

        /* Getting Started */
        .getting-started {
            margin-bottom: 6rem;
        }

        .getting-started h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .getting-started-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }

        .getting-started-card {
            background: var(--gray-50);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .getting-started-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .getting-started-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .getting-started-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .getting-started-description {
            color: var(--gray-600);
            line-height: 1.6;
        }

        /* API Reference */
        .api-reference {
            margin-bottom: 6rem;
        }

        .api-reference h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .api-endpoints {
            background: var(--gray-50);
            border-radius: 20px;
            overflow: hidden;
        }

        .endpoint-category {
            border-bottom: 1px solid var(--gray-200);
        }

        .endpoint-category:last-child {
            border-bottom: none;
        }

        .endpoint-header {
            padding: 2rem;
            background: var(--white);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .endpoint-header:hover {
            background: var(--gray-50);
        }

        .endpoint-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .endpoint-toggle {
            font-size: 1.5rem;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .endpoint-category.active .endpoint-toggle {
            transform: rotate(45deg);
        }

        .endpoint-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: var(--white);
        }

        .endpoint-category.active .endpoint-content {
            max-height: 1000px;
        }

        .endpoint-list {
            padding: 0 2rem 2rem;
        }

        .endpoint-item {
            background: var(--gray-50);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .endpoint-method {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.5rem;
        }

        .method-get {
            background: var(--success);
        }

        .method-post {
            background: var(--warning);
        }

        .method-put {
            background: var(--primary);
        }

        .method-delete {
            background: var(--danger);
        }

        .endpoint-url {
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .endpoint-description {
            color: var(--gray-600);
            line-height: 1.5;
        }

        /* Code Examples */
        .code-examples {
            background: var(--gray-50);
            padding: 6rem 0;
            margin: 6rem 0;
        }

        .code-examples h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .code-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 3rem;
        }

        .code-tab {
            padding: 1rem 2rem;
            background: var(--white);
            border: 2px solid var(--gray-200);
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
            border-radius: 10px 10px 0 0;
        }

        .code-tab.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .code-content {
            background: var(--white);
            border: 2px solid var(--gray-200);
            border-radius: 0 10px 10px 10px;
            padding: 2rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.5;
            overflow-x: auto;
        }

        /* SDK Section */
        .sdk-section {
            margin-bottom: 6rem;
        }

        .sdk-section h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 4rem;
        }

        .sdk-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .sdk-item {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .sdk-item:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 15px 35px rgba(64, 153, 255, 0.1);
        }

        .sdk-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .sdk-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .sdk-description {
            color: var(--gray-600);
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* CTA Section */
        .api-cta {
            background: var(--gray-50);
            padding: 6rem 0;
            text-align: center;
        }

        .api-cta h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
        }

        .api-cta p {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .api-cta-buttons {
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

            .getting-started-grid,
            .sdk-grid {
                grid-template-columns: 1fr;
            }

            .code-tabs {
                flex-direction: column;
                align-items: center;
            }

            .code-tab {
                margin: 0.25rem 0;
                border-radius: 10px;
            }

            .api-cta h2 {
                font-size: 2rem;
            }

            .api-cta-buttons {
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
            <h1>API Documentation</h1>
            <p>Comprehensive documentation for integrating with the MTravels API. Build powerful applications and automate your travel business operations.</p>
        </div>
    </section>

    <!-- API Documentation Content -->
    <section class="api-content">
        <div class="container">
            <!-- Getting Started -->
            <div class="getting-started">
                <h2>Getting Started</h2>
                <div class="getting-started-grid">
                    <div class="getting-started-card">
                        <div class="getting-started-icon">🔑</div>
                        <h3 class="getting-started-title">Authentication</h3>
                        <p class="getting-started-description">Learn how to authenticate your API requests using OAuth 2.0 and API keys for secure access to MTravels platform.</p>
                    </div>
                    <div class="getting-started-card">
                        <div class="getting-started-icon">📋</div>
                        <h3 class="getting-started-title">Rate Limiting</h3>
                        <p class="getting-started-description">Understand API rate limits and best practices for efficient API usage and avoiding throttling.</p>
                    </div>
                    <div class="getting-started-card">
                        <div class="getting-started-icon">🔄</div>
                        <h3 class="getting-started-title">Webhooks</h3>
                        <p class="getting-started-description">Set up webhooks to receive real-time notifications about booking updates, payments, and system events.</p>
                    </div>
                    <div class="getting-started-card">
                        <div class="getting-started-icon">📊</div>
                        <h3 class="getting-started-title">API Analytics</h3>
                        <p class="getting-started-description">Monitor your API usage, track performance metrics, and optimize your integration for better results.</p>
                    </div>
                    <div class="getting-started-card">
                        <div class="getting-started-icon">🛠️</div>
                        <h3 class="getting-started-title">SDK & Libraries</h3>
                        <p class="getting-started-description">Use our official SDKs and libraries for popular programming languages to speed up development.</p>
                    </div>
                    <div class="getting-started-card">
                        <div class="getting-started-icon">📞</div>
                        <h3 class="getting-started-title">Support</h3>
                        <p class="getting-started-description">Get help from our developer community, submit bug reports, and request new API features.</p>
                    </div>
                </div>
            </div>

            <!-- API Reference -->
            <div class="api-reference">
                <h2>API Reference</h2>
                <div class="api-endpoints">
                    <!-- Bookings API -->
                    <div class="endpoint-category">
                        <div class="endpoint-header">
                            <h3 class="endpoint-title">Bookings API</h3>
                            <span class="endpoint-toggle">+</span>
                        </div>
                        <div class="endpoint-content">
                            <div class="endpoint-list">
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/bookings</div>
                                    <div class="endpoint-description">Retrieve a list of bookings with optional filtering and pagination.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/bookings/{id}</div>
                                    <div class="endpoint-description">Get detailed information about a specific booking.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-post">POST</span>
                                    <div class="endpoint-url">/api/v1/bookings</div>
                                    <div class="endpoint-description">Create a new booking with customer and service details.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-put">PUT</span>
                                    <div class="endpoint-url">/api/v1/bookings/{id}</div>
                                    <div class="endpoint-description">Update an existing booking with new information.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customers API -->
                    <div class="endpoint-category">
                        <div class="endpoint-header">
                            <h3 class="endpoint-title">Customers API</h3>
                            <span class="endpoint-toggle">+</span>
                        </div>
                        <div class="endpoint-content">
                            <div class="endpoint-list">
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/customers</div>
                                    <div class="endpoint-description">List all customers with search and filter options.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-post">POST</span>
                                    <div class="endpoint-url">/api/v1/customers</div>
                                    <div class="endpoint-description">Create a new customer profile in the system.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/customers/{id}</div>
                                    <div class="endpoint-description">Retrieve detailed customer information and booking history.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments API -->
                    <div class="endpoint-category">
                        <div class="endpoint-header">
                            <h3 class="endpoint-title">Payments API</h3>
                            <span class="endpoint-toggle">+</span>
                        </div>
                        <div class="endpoint-content">
                            <div class="endpoint-list">
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-post">POST</span>
                                    <div class="endpoint-url">/api/v1/payments</div>
                                    <div class="endpoint-description">Process a payment for a booking or service.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/payments/{id}</div>
                                    <div class="endpoint-description">Get payment details and transaction status.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-post">POST</span>
                                    <div class="endpoint-url">/api/v1/refunds</div>
                                    <div class="endpoint-description">Process a refund for a completed payment.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reports API -->
                    <div class="endpoint-category">
                        <div class="endpoint-header">
                            <h3 class="endpoint-title">Reports API</h3>
                            <span class="endpoint-toggle">+</span>
                        </div>
                        <div class="endpoint-content">
                            <div class="endpoint-list">
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/reports/sales</div>
                                    <div class="endpoint-description">Generate sales and revenue reports with date filtering.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/reports/bookings</div>
                                    <div class="endpoint-description">Get booking statistics and performance metrics.</div>
                                </div>
                                <div class="endpoint-item">
                                    <span class="endpoint-method method-get">GET</span>
                                    <div class="endpoint-url">/api/v1/reports/financial</div>
                                    <div class="endpoint-description">Access financial reports and profit/loss statements.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Examples -->
            <div class="code-examples">
                <div class="container">
                    <h2>Code Examples</h2>
                    <div class="code-tabs">
                        <div class="code-tab active" data-lang="curl">cURL</div>
                        <div class="code-tab" data-lang="php">PHP</div>
                        <div class="code-tab" data-lang="python">Python</div>
                        <div class="code-tab" data-lang="javascript">JavaScript</div>
                    </div>
                    <div class="code-content">
                        <pre id="code-display">// Get all bookings
curl -X GET "https://api.mtravels.com/v1/bookings" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json"

// Create a new booking
curl -X POST "https://api.mtravels.com/v1/bookings" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_id": "12345",
    "service_type": "flight",
    "booking_details": {
      "origin": "KBL",
      "destination": "DXB",
      "departure_date": "2024-12-01"
    }
  }'</pre>
                    </div>
                </div>
            </div>

            <!-- SDK Section -->
            <div class="sdk-section">
                <h2>SDKs & Libraries</h2>
                <div class="sdk-grid">
                    <div class="sdk-item">
                        <div class="sdk-icon">🐘</div>
                        <h3 class="sdk-title">PHP SDK</h3>
                        <p class="sdk-description">Official PHP library with full API support, error handling, and easy integration.</p>
                    </div>
                    <div class="sdk-item">
                        <div class="sdk-icon">🐍</div>
                        <h3 class="sdk-title">Python SDK</h3>
                        <p class="sdk-description">Python library for seamless integration with Django, Flask, and other frameworks.</p>
                    </div>
                    <div class="sdk-item">
                        <div class="sdk-icon">📱</div>
                        <h3 class="sdk-title">JavaScript SDK</h3>
                        <p class="sdk-description">Browser and Node.js compatible library for web applications and server-side integration.</p>
                    </div>
                    <div class="sdk-item">
                        <div class="sdk-icon">☕</div>
                        <h3 class="sdk-title">Java SDK</h3>
                        <p class="sdk-description">Enterprise-grade Java library for Spring Boot and other Java frameworks.</p>
                    </div>
                    <div class="sdk-item">
                        <div class="sdk-icon">⚛️</div>
                        <h3 class="sdk-title">React Components</h3>
                        <p class="sdk-description">Pre-built React components for booking forms, payment processing, and user dashboards.</p>
                    </div>
                    <div class="sdk-item">
                        <div class="sdk-icon">🔧</div>
                        <h3 class="sdk-title">Postman Collection</h3>
                        <p class="sdk-description">Complete Postman collection with all API endpoints and example requests.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="api-cta">
        <div class="container">
            <h2>Ready to Build?</h2>
            <p>Start integrating with MTravels API today. Get your API key and begin building powerful travel solutions.</p>
            <div class="api-cta-buttons">
                <a href="book-demo.php" class="btn btn-primary">Get API Access</a>
                <a href="mailto:api-support@mtravels.com" class="btn" style="background: transparent; color: var(--primary); border: 2px solid var(--primary);">Contact API Team</a>
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

        // API endpoint accordion functionality
        document.querySelectorAll('.endpoint-header').forEach(header => {
            header.addEventListener('click', function() {
                const category = this.parentElement;
                const isActive = category.classList.contains('active');

                // Close all categories
                document.querySelectorAll('.endpoint-category').forEach(cat => {
                    cat.classList.remove('active');
                });

                // Open clicked category if it wasn't active
                if (!isActive) {
                    category.classList.add('active');
                }
            });
        });

        // Code example tabs
        const codeExamples = {
            curl: `// Get all bookings
curl -X GET "https://api.mtravels.com/v1/bookings" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json"

// Create a new booking
curl -X POST "https://api.mtravels.com/v1/bookings" \\
  -H "Authorization: Bearer YOUR_API_TOKEN" \\
  -H "Content-Type: application/json" \\
  -d '{
    "customer_id": "12345",
    "service_type": "flight",
    "booking_details": {
      "origin": "KBL",
      "destination": "DXB",
      "departure_date": "2024-12-01"
    }
  }'`,

            php: `<?php
// Initialize MTravels API client
$client = new MTravelsAPI('YOUR_API_TOKEN');

// Get all bookings
$bookings = $client->get('/bookings');

// Create a new booking
$newBooking = [
    'customer_id' => '12345',
    'service_type' => 'flight',
    'booking_details' => [
        'origin' => 'KBL',
        'destination' => 'DXB',
        'departure_date' => '2024-12-01'
    ]
];

$result = $client->post('/bookings', $newBooking);
echo $result['id']; // Booking ID
?>`,

            python: `# Initialize MTravels API client
client = MTravelsAPI('YOUR_API_TOKEN')

# Get all bookings
bookings = client.get('/bookings')

# Create a new booking
new_booking = {
    'customer_id': '12345',
    'service_type': 'flight',
    'booking_details': {
        'origin': 'KBL',
        'destination': 'DXB',
        'departure_date': '2024-12-01'
    }
}

result = client.post('/bookings', new_booking)
print(result['id'])  # Booking ID`,

            javascript: `// Initialize MTravels API client
const client = new MTravelsAPI('YOUR_API_TOKEN');

// Get all bookings
client.get('/bookings')
  .then(bookings => {
    console.log(bookings);
  });

// Create a new booking
const newBooking = {
  customer_id: '12345',
  service_type: 'flight',
  booking_details: {
    origin: 'KBL',
    destination: 'DXB',
    departure_date: '2024-12-01'
  }
};

client.post('/bookings', newBooking)
  .then(result => {
    console.log('Booking created:', result.id);
  });`
        };

        document.querySelectorAll('.code-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.code-tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');

                // Update code display
                const lang = this.dataset.lang;
                document.getElementById('code-display').textContent = codeExamples[lang];
            });
        });
    </script>
</body>
</html>