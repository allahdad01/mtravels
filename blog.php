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

// Optimized function to fetch blog posts with caching
function getBlogPosts($pdo, $limit = null) {
    $cache_key = getCacheKey('blog_posts', [$limit]);

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $sql = "SELECT `id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at` FROM `blog_posts` WHERE `status` = 'published' ORDER BY `created_at` DESC";
        $params = [];

        if ($limit !== null) {
            $sql .= " LIMIT ?";
            $params[] = $limit;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $blog_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        setCachedData($cache_key, $blog_posts);
        return $blog_posts;
    } catch (PDOException $e) {
        error_log("Error fetching blog posts: " . $e->getMessage());
        return [];
    }
}

// Helper function to get setting value
function getSetting($settings, $key, $default = '') {
    return isset($settings[$key]) ? htmlspecialchars($settings[$key]) : $default;
}

// Helper function to format date
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// Fetch data
$platform_settings = getPlatformSettings($pdo);
$blog_posts = getBlogPosts($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Stay updated with the latest insights, trends, and best practices in travel agency management and technology.">
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

        /* Blog Content */
        .blog-content {
            padding: 6rem 0;
            background: var(--white);
        }

        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 3rem;
        }

        .blog-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-200);
        }

        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(64, 153, 255, 0.1);
        }

        .blog-image {
            width: 100%;
            height: 250px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }

        .blog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .blog-content-card {
            padding: 2rem;
        }

        .blog-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .blog-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .blog-date {
            color: var(--gray-400);
        }

        .blog-read-time {
            color: var(--gray-400);
            margin-left: auto;
        }

        .blog-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
            line-height: 1.3;
        }

        .blog-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .blog-title a:hover {
            color: var(--primary);
        }

        .blog-excerpt {
            color: var(--gray-600);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .blog-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .blog-tag {
            background: var(--gray-100);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .read-more {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .read-more:hover {
            gap: 1rem;
            color: var(--primary-dark);
        }

        /* Featured Post */
        .featured-post {
            margin-bottom: 4rem;
        }

        .featured-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }

        .featured-image {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            overflow: hidden;
        }

        .featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .featured-content {
            padding: 3rem;
        }

        .featured-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: var(--gray-500);
        }

        .featured-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .featured-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }

        .featured-title a:hover {
            color: var(--primary);
        }

        .featured-excerpt {
            font-size: 1.1rem;
            color: var(--gray-600);
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        /* Newsletter Section */
        .newsletter {
            padding: 6rem 0;
            background: var(--gray-50);
            text-align: center;
        }

        .newsletter h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .newsletter p {
            font-size: 1.1rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .newsletter-form {
            display: flex;
            gap: 1rem;
            max-width: 500px;
            margin: 0 auto;
            flex-wrap: wrap;
            justify-content: center;
        }

        .newsletter-input {
            flex: 1;
            min-width: 250px;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .newsletter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(183, 197, 240, 0.2);
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

            .blog-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .blog-card {
                margin: 0 -1rem;
            }

            .featured-title {
                font-size: 2rem;
            }

            .featured-content {
                padding: 2rem;
            }

            .newsletter h2 {
                font-size: 2rem;
            }

            .newsletter-form {
                flex-direction: column;
                align-items: center;
            }

            .newsletter-input {
                min-width: auto;
                width: 100%;
                max-width: 300px;
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
                        <li><a href="blog.php">Blog</a></li>
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
            <h1>Travel Tech Insights</h1>
            <p>Stay updated with the latest trends, best practices, and innovations in travel agency management and technology.</p>
        </div>
    </section>

    <!-- Blog Content -->
    <section class="blog-content">
        <div class="container">
            <?php if (!empty($blog_posts)): ?>
                <!-- Featured Post -->
                <?php $featured_post = array_shift($blog_posts); ?>
                <div class="featured-post">
                    <div class="featured-card">
                        <div class="featured-image">
                            <?php if (!empty($featured_post['featured_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($featured_post['featured_image']); ?>" alt="<?php echo htmlspecialchars($featured_post['title']); ?>">
                            <?php else: ?>
                                📝
                            <?php endif; ?>
                        </div>
                        <div class="featured-content">
                            <div class="featured-meta">
                            <div class="blog-author">
                            👤 <?php echo htmlspecialchars($featured_post['author'] ?? 'MTravels Team'); ?>
                            </div>
                            <div class="blog-date"><?php echo formatDate($featured_post['created_at']); ?></div>
                            <?php if (!empty($featured_post['category'])): ?>
                                <div class="blog-category"><?php echo htmlspecialchars($featured_post['category']); ?></div>
                                 <?php endif; ?>
                             </div>
                            <h2 class="featured-title">
                                <a href="#"><?php echo htmlspecialchars($featured_post['title']); ?></a>
                            </h2>
                            <p class="featured-excerpt"><?php echo htmlspecialchars($featured_post['excerpt']); ?></p>
                            <div class="blog-tags">
                                <span class="blog-tag">Featured</span>
                                <span class="blog-tag">Travel Tech</span>
                            </div>
                            <a href="#" class="read-more">Read Full Article →</a>
                        </div>
                    </div>
                </div>

                <!-- Blog Grid -->
                <div class="blog-grid">
                    <?php foreach ($blog_posts as $post): ?>
                    <div class="blog-card">
                        <div class="blog-image">
                            <?php if (!empty($post['featured_image'])): ?>
                                <img src=".<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <?php else: ?>
                                📄
                            <?php endif; ?>
                        </div>
                        <div class="blog-content-card">
                        <div class="blog-meta">
                        <div class="blog-author">
                        👤 <?php echo htmlspecialchars($post['author'] ?? 'MTravels Team'); ?>
                        </div>
                        <div class="blog-date"><?php echo formatDate($post['created_at']); ?></div>
                        <?php if (!empty($post['category'])): ?>
                            <div class="blog-category"><?php echo htmlspecialchars($post['category']); ?></div>
                                 <?php endif; ?>
                             </div>
                            <h3 class="blog-title">
                                <a href="#"><?php echo htmlspecialchars($post['title']); ?></a>
                            </h3>
                            <p class="blog-excerpt"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                            <div class="blog-tags">
                                <span class="blog-tag">Travel</span>
                                <span class="blog-tag">Technology</span>
                            </div>
                            <a href="#" class="read-more">Read More →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Default Blog Posts -->
                <div class="featured-post">
                    <div class="featured-card">
                        <div class="featured-image">🚀</div>
                        <div class="featured-content">
                            <div class="featured-meta">
                                <div class="blog-author">👤 MTravels Team</div>
                                <div class="blog-date">Nov 5, 2024</div>
                                <div class="blog-read-time">5 min read</div>
                            </div>
                            <h2 class="featured-title">
                                <a href="#">The Future of Travel Agency Management: AI and Automation</a>
                            </h2>
                            <p class="featured-excerpt">Discover how artificial intelligence and automation are transforming the travel industry, making operations more efficient and customer experiences more personalized.</p>
                            <div class="blog-tags">
                                <span class="blog-tag">Featured</span>
                                <span class="blog-tag">AI</span>
                                <span class="blog-tag">Automation</span>
                            </div>
                            <a href="#" class="read-more">Read Full Article →</a>
                        </div>
                    </div>
                </div>

                <div class="blog-grid">
                    <div class="blog-card">
                        <div class="blog-image">💰</div>
                        <div class="blog-content-card">
                            <div class="blog-meta">
                                <div class="blog-author">👤 MTravels Team</div>
                                <div class="blog-date">Nov 3, 2024</div>
                                <div class="blog-read-time">4 min read</div>
                            </div>
                            <h3 class="blog-title">
                                <a href="#">Maximizing Profit Margins in Travel Agencies</a>
                            </h3>
                            <p class="blog-excerpt">Learn proven strategies for improving profitability through better pricing, cost management, and operational efficiency.</p>
                            <div class="blog-tags">
                                <span class="blog-tag">Finance</span>
                                <span class="blog-tag">Strategy</span>
                            </div>
                            <a href="#" class="read-more">Read More →</a>
                        </div>
                    </div>

                    <div class="blog-card">
                        <div class="blog-image">📱</div>
                        <div class="blog-content-card">
                            <div class="blog-meta">
                                <div class="blog-author">👤 MTravels Team</div>
                                <div class="blog-date">Nov 1, 2024</div>
                                <div class="blog-read-time">3 min read</div>
                            </div>
                            <h3 class="blog-title">
                                <a href="#">Digital Transformation in Travel: What You Need to Know</a>
                            </h3>
                            <p class="blog-excerpt">Explore the digital tools and technologies that are reshaping how travel agencies interact with customers and manage operations.</p>
                            <div class="blog-tags">
                                <span class="blog-tag">Digital</span>
                                <span class="blog-tag">Technology</span>
                            </div>
                            <a href="#" class="read-more">Read More →</a>
                        </div>
                    </div>

                    <div class="blog-card">
                        <div class="blog-image">👥</div>
                        <div class="blog-content-card">
                            <div class="blog-meta">
                                <div class="blog-author">👤 MTravels Team</div>
                                <div class="blog-date">Oct 28, 2024</div>
                                <div class="blog-read-time">4 min read</div>
                            </div>
                            <h3 class="blog-title">
                                <a href="#">Building Strong Customer Relationships in Travel</a>
                            </h3>
                            <p class="blog-excerpt">Discover strategies for creating lasting customer relationships and improving satisfaction in the competitive travel market.</p>
                            <div class="blog-tags">
                                <span class="blog-tag">Customer Service</span>
                                <span class="blog-tag">Strategy</span>
                            </div>
                            <a href="#" class="read-more">Read More →</a>
                        </div>
                    </div>

                    <div class="blog-card">
                        <div class="blog-image">📊</div>
                        <div class="blog-content-card">
                            <div class="blog-meta">
                                <div class="blog-author">👤 MTravels Team</div>
                                <div class="blog-date">Oct 25, 2024</div>
                                <div class="blog-read-time">3 min read</div>
                            </div>
                            <h3 class="blog-title">
                                <a href="#">Data-Driven Decision Making for Travel Agencies</a>
                            </h3>
                            <p class="blog-excerpt">Learn how to leverage data analytics to make informed decisions and optimize your travel agency's performance.</p>
                            <div class="blog-tags">
                                <span class="blog-tag">Analytics</span>
                                <span class="blog-tag">Strategy</span>
                            </div>
                            <a href="#" class="read-more">Read More →</a>
                        </div>
                    </div>

                    <div class="blog-card">
                        <div class="blog-image">🔒</div>
                        <div class="blog-content-card">
                            <div class="blog-meta">
                                <div class="blog-author">👤 MTravels Team</div>
                                <div class="blog-date">Oct 22, 2024</div>
                                <div class="blog-read-time">4 min read</div>
                            </div>
                            <h3 class="blog-title">
                                <a href="#">Travel Agency Security: Protecting Your Business and Customers</a>
                            </h3>
                            <p class="blog-excerpt">Essential security practices and technologies to protect sensitive customer data and maintain trust in your travel business.</p>
                            <div class="blog-tags">
                                <span class="blog-tag">Security</span>
                                <span class="blog-tag">Technology</span>
                            </div>
                            <a href="#" class="read-more">Read More →</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="newsletter">
        <div class="container">
            <h2>Stay Updated</h2>
            <p>Subscribe to our newsletter and get the latest insights on travel technology, industry trends, and best practices delivered to your inbox.</p>
            <form class="newsletter-form">
                <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
                <button type="submit" class="btn btn-primary">Subscribe</button>
            </form>
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

        // Newsletter form submission
        document.querySelector('.newsletter-form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Thank you for subscribing! We\'ll keep you updated with the latest travel tech insights.');
            this.reset();
        });
    </script>
</body>
</html>