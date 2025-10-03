
<?php
session_start();

// Database connection and security
require_once 'includes/db.php';

// Default tenant ID for landing page (can be made configurable)
$default_tenant_id = 1;

// Cache settings (TTL in seconds)
$cache_ttl = 3600; // 1 hour
$cache_dir = __DIR__ . '/cache/';

// Create cache directory if it doesn't exist
if (!is_dir($cache_dir)) {
    mkdir($cache_dir, 0755, true);
}

// Function to get cache key
function getCacheKey($prefix, $params = []) {
    return $prefix . '_' . md5(serialize($params));
}

// Function to get cached data
function getCachedData($key) {
    global $cache_dir, $cache_ttl;
    $file = $cache_dir . $key . '.cache';

    if (file_exists($file) && (time() - filemtime($file)) < $cache_ttl) {
        return unserialize(file_get_contents($file));
    }
    return false;
}

// Function to set cached data
function setCachedData($key, $data) {
    global $cache_dir;
    $file = $cache_dir . $key . '.cache';
    file_put_contents($file, serialize($data));
}

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

// Optimized function to fetch plans with caching
function getPlans($pdo) {
    $cache_key = getCacheKey('plans');

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, description, features, price, max_users, trial_days FROM plans WHERE status = 'active' ORDER BY price ASC");
        $stmt->execute();
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        setCachedData($cache_key, $plans);
        return $plans;
    } catch (PDOException $e) {
        error_log("Error fetching plans: " . $e->getMessage());
        return [];
    }
}

// Optimized function to fetch destinations with caching
function getDestinations($pdo, $tenant_id, $limit = 6) {
    $cache_key = getCacheKey('destinations', [$tenant_id, $limit]);

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, short_description, image, rating, price FROM destinations WHERE tenant_id = ? AND featured = 1 AND active = 1 ORDER BY rating DESC, created_at DESC LIMIT ?");
        $stmt->execute([$tenant_id, $limit]);
        $destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        setCachedData($cache_key, $destinations);
        return $destinations;
    } catch (PDOException $e) {
        error_log("Error fetching destinations: " . $e->getMessage());
        return [];
    }
}

// Optimized function to fetch testimonials with caching
function getTestimonials($pdo, $tenant_id, $limit = 3) {
    $cache_key = getCacheKey('testimonials', [$tenant_id, $limit]);

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, name, photo, testimonial, rating FROM testimonials WHERE tenant_id = ? AND active = 1 ORDER BY rating DESC, created_at DESC LIMIT ?");
        $stmt->execute([$tenant_id, $limit]);
        $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        setCachedData($cache_key, $testimonials);
        return $testimonials;
    } catch (PDOException $e) {
        error_log("Error fetching testimonials: " . $e->getMessage());
        return [];
    }
}

// Optimized function to fetch deals with caching
function getDeals($pdo, $tenant_id, $limit = 3) {
    $cache_key = getCacheKey('deals', [$tenant_id, $limit]);

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, title, description, image, old_price, new_price, discount, start_date, end_date FROM deals WHERE tenant_id = ? AND active = 1 AND end_date >= CURDATE() ORDER BY discount DESC, created_at DESC LIMIT ?");
        $stmt->execute([$tenant_id, $limit]);
        $deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

        setCachedData($cache_key, $deals);
        return $deals;
    } catch (PDOException $e) {
        error_log("Error fetching deals: " . $e->getMessage());
        return [];
    }
}

// Optimized function to fetch blog posts with caching
function getBlogPosts($pdo, $tenant_id, $limit = 3) {
    $cache_key = getCacheKey('blog_posts', [$tenant_id, $limit]);

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, featured_image, created_at FROM blog_posts WHERE tenant_id = ? AND status = 'published' ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$tenant_id, $limit]);
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

// Helper function to format currency
function formatCurrency($amount, $currency = 'USD') {
    return $currency . ' ' . number_format($amount, 2);
}

// Helper function to format feature names from snake_case to Title Case
function formatFeatureName($feature) {
    return ucwords(str_replace('_', ' ', $feature));
}

// Fetch all data with error handling
try {
    $platform_settings = getPlatformSettings($pdo);
    $plans = getPlans($pdo);
    $destinations = getDestinations($pdo, $default_tenant_id);
    $testimonials = getTestimonials($pdo, $default_tenant_id);
    $deals = getDeals($pdo, $default_tenant_id);
    $blog_posts = getBlogPosts($pdo, $default_tenant_id);
} catch (Exception $e) {
    error_log("Error loading landing page data: " . $e->getMessage());
    // Provide fallback data
    $platform_settings = [];
    $plans = [];
    $destinations = [];
    $testimonials = [];
    $deals = [];
    $blog_posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSetting($platform_settings, 'platform_name', 'MTravels') . ' - ' . getSetting($platform_settings, 'platform_description', 'Advanced Travel Agency SaaS Platform'); ?></title>
    <meta name="description" content="<?php echo getSetting($platform_settings, 'platform_description', 'The most advanced SaaS platform for modern travel agencies. Streamline operations, boost sales, and delight customers.'); ?>">
        <!-- Favicon -->
            <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: <?php echo getSetting($platform_settings, 'primary_color', '#4099ff'); ?>;
            --primary-dark: <?php echo getSetting($platform_settings, 'primary_color', '#2ed8b6'); ?>;
            --primary-light: <?php echo getSetting($platform_settings, 'primary_color', '#a0e6ff'); ?>;
            --secondary: <?php echo getSetting($platform_settings, 'secondary_color', '#2ed8b6'); ?>;
            --accent: <?php echo getSetting($platform_settings, 'accent_color', '#06b6d4'); ?>;
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

        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
        }

        .animated-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-20px) rotate(1deg); }
            66% { transform: translateY(-10px) rotate(-1deg); }
        }

        /* Floating Elements */
        .floating-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .floating-element {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            backdrop-filter: blur(10px);
            animation: floatUp 15s infinite linear;
        }

        .floating-element:nth-child(1) { left: 10%; width: 80px; height: 80px; animation-delay: 0s; }
        .floating-element:nth-child(2) { left: 20%; width: 60px; height: 60px; animation-delay: -2s; }
        .floating-element:nth-child(3) { left: 35%; width: 100px; height: 100px; animation-delay: -4s; }
        .floating-element:nth-child(4) { left: 50%; width: 50px; height: 50px; animation-delay: -6s; }
        .floating-element:nth-child(5) { left: 70%; width: 75px; height: 75px; animation-delay: -8s; }
        .floating-element:nth-child(6) { left: 85%; width: 90px; height: 90px; animation-delay: -10s; }

        @keyframes floatUp {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
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

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
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

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: var(--primary);
            transition: width 0.3s;
        }

        .nav-links a:hover::after {
            width: 100%;
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

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
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

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.3);
        }

        /* Hero Section */
        .hero {
            padding: 12rem 0 8rem;
            position: relative;
            background: var(--gray-50);
        }

        .hero-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-text {
            animation: slideInLeft 1s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-text h1 {
            font-size: 4rem;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }

        .hero-text h1::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 0;
            width: 100px;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 2px;
            animation: expand 2s ease-out 0.5s both;
        }

        @keyframes expand {
            from { width: 0; }
            to { width: 100px; }
        }

        .hero-text .subtitle {
            font-size: 1.25rem;
            color: var(--gray-600);
            margin-bottom: 2rem;
            line-height: 1.7;
            animation: slideInLeft 1s ease-out 0.2s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 3rem;
            animation: slideInLeft 1s ease-out 0.4s both;
        }

        .trust-indicators {
            display: flex;
            align-items: center;
            gap: 2rem;
            animation: slideInLeft 1s ease-out 0.6s both;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            font-weight: 600;
        }

        .hero-image {
            position: relative;
            animation: slideInRight 1s ease-out 0.3s both;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }


        /* Features Section */
        .features {
            padding: 6rem 0;
            background: var(--white);
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 3rem;
            font-weight: 800;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .section-header p {
            font-size: 1.2rem;
            color: var(--gray-600);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%) !important;
            color: #ffffff !important;
            border-bottom: none !important;
            padding: 3rem 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: none;
            transition: all 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: left 0.5s ease;
        }

        .feature-card:hover::before {
            left: 0;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 60px rgba(64, 153, 255, 0.2);
            border-color: var(--primary-dark);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            position: relative;
            color: white;
        }

        .feature-icon::after {
            content: '';
            position: absolute;
            inset: -5px;
            background: inherit;
            border-radius: inherit;
            z-index: -1;
            opacity: 0.2;
            filter: blur(10px);
        }

        .feature-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #ffffff;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .feature-link {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: gap 0.3s ease;
        }

        .feature-link:hover {
            gap: 1rem;
        }

        /* Stats Section */
        .stats {
            padding: 8rem 0;
            background: var(--gray-50);
            color: var(--gray-900);
            position: relative;
            overflow: hidden;
        }

        .stats::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="25" cy="25" r="2" fill="rgba(64, 153, 255, 0.1)"/><circle cx="75" cy="25" r="2" fill="rgba(64, 153, 255, 0.1)"/><circle cx="25" cy="75" r="2" fill="rgba(64, 153, 255, 0.1)"/><circle cx="75" cy="75" r="2" fill="rgba(64, 153, 255, 0.1)"/></svg>');
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            position: relative;
            z-index: 1;
        }

        .stat-item {
            text-align: center;
            animation: countUp 2s ease-out;
            background: var(--white);
            border-radius: 20px;
            padding: 3rem 2rem;
            border: 1px solid var(--gray-200);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(64, 153, 255, 0.1);
        }

        @keyframes countUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .stat-label {
            font-size: 1.2rem;
            font-weight: 600;
            opacity: 0.9;
        }

        /* Contact Section */
        .contact {
            padding: 6rem 0;
            background: var(--white);
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(64, 153, 255, 0.1);
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            color: var(--gray-900);
        }

        .contact-details h4 {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .contact-details p {
            color: var(--gray-600);
            margin: 0;
        }

        .contact-details a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .contact-details a:hover {
            text-decoration: underline;
        }

        .contact-form {
            background: var(--gray-50);
            padding: 3rem;
            border-radius: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(183, 197, 240, 0.2);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* Testimonials Section */
        .testimonials {
            padding: 6rem 0;
            background: var(--gray-50);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .testimonial-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            position: relative;
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(64, 153, 255, 0.1);
        }

        .testimonial-content {
            font-style: italic;
            color: var(--gray-600);
            margin-bottom: 1.5rem;
            position: relative;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .testimonial-content::before {
            content: '"';
            font-size: 4rem;
            color: var(--primary);
            position: absolute;
            top: -20px;
            left: -10px;
            opacity: 0.2;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .author-info h4 {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .author-rating {
            color: var(--secondary);
            font-size: 1.2rem;
        }

        /* Pricing Section */
        .pricing {
            padding: 6rem 0;
            background: var(--white);
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .pricing-card {
            background: var(--white);
            border: 2px solid var(--gray-100);
            border-radius: 20px;
            padding: 3rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            min-height: 500px; /* Consistent minimum height */
            display: flex;
            flex-direction: column;
        }

        .pricing-card.popular {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .pricing-card.popular::before {
            content: 'Most Popular';
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .pricing-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(64, 153, 255, 0.1);
        }

        .pricing-card > *:last-child {
            margin-top: auto; /* Push button to bottom */
        }

        .pricing-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .pricing-price {
            font-size: 3rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .pricing-period {
            color: var(--gray-600);
            margin-bottom: 2rem;
        }

        .pricing-features {
            margin: 2rem 0;
            flex: 1; /* Allow features to grow and fill space */
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .feature-group {
            margin-bottom: 1.5rem;
        }

        .feature-group:last-child {
            margin-bottom: 0;
        }

        .feature-group-title {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .feature-group-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .feature-item {
            padding: 0.2rem 0;
            color: var(--gray-600);
            line-height: 1.3;
            font-size: 0.85rem;
            position: relative;
            padding-left: 1rem;
        }

        .feature-item::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: var(--success);
            font-weight: bold;
            font-size: 0.8rem;
        }

        .feature-highlight {
            background: var(--gray-50);
            padding: 0.6rem 0.8rem;
            margin: 0.3rem 0;
            border-radius: 6px;
            border-left: 3px solid var(--primary);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.85rem;
        }

        .pricing-features li::before {
            content: '✓';
            color: var(--success);
            margin-right: 0.5rem;
        }

        /* CTA Section */
        .cta {
            padding: 8rem 0;
            background: var(--gray-50);
            text-align: center;
        }

        .cta h2 {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--gray-900);
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .cta p {
            font-size: 1.3rem;
            color: var(--gray-600);
            margin-bottom: 3rem;
            max-width: 700px;
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
            padding: 6rem 0 3rem;
            position: relative;
        }

        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 3rem;
            margin-bottom: 3rem;
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

        .footer p {
            color: var(--gray-600) !important;
        }

        .footer-bottom {
            padding-top: 3rem;
            border-top: 1px solid var(--primary);
            text-align: center;
            color: var(--gray-600);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero-text h1 {
                font-size: 2.5rem;
            }

            .hero-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-buttons {
                justify-content: center;
            }

            .trust-indicators {
                justify-content: center;
                flex-wrap: wrap;
            }

            .section-header h2 {
                font-size: 2rem;
            }

            .contact-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .contact-info {
                gap: 1.5rem;
            }

            .contact-item {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
                padding: 1rem;
            }

            .contact-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }

            .contact-form {
                padding: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .testimonials-grid {
                grid-template-columns: 1fr;
            }

            .testimonial-card {
                padding: 1.5rem;
            }

            .testimonial-author {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .cta h2 {
                font-size: 2.5rem;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 480px) {
            .contact-item {
                padding: 1rem 0.5rem;
            }

            .contact-form {
                padding: 1.5rem;
            }

            .testimonial-content {
                font-size: 1rem;
            }

            .testimonial-content::before {
                font-size: 3rem;
                top: -15px;
                left: -5px;
            }
        }
    </style>
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

    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="nav-content">
                <a href="#" class="logo"><img src="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'logo.png') ?>" alt="Logo" style="height: 40px;"> <span class="logo-text"><?= htmlspecialchars(getSetting($platform_settings, 'platform_name') ?? 'MTravels') ?></span></a>
                <ul class="nav-links">
                    <li><a href="#features">Features</a></li>
                    <li><a href="#pricing">Pricing</a></li>
                    <li><a href="#testimonials">Reviews</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="login.php" class="nav-login-link" style="color: var(--primary); text-decoration: none; font-weight: 600; transition: color 0.3s;">Login</a>
                    <a href="book-demo.php" class="btn btn-primary">
                        <span>Book a Demo</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h1><?php echo getSetting($platform_settings, 'hero_title', 'Streamline Your Travel Operations with ' . getSetting($platform_settings, 'platform_name', 'MTravels')); ?></h1>
                    <p class="subtitle">
                        <?php echo getSetting($platform_settings, 'hero_subtitle', 'Professional travel agency management platform designed to optimize workflows, enhance customer service, and drive business growth through comprehensive automation and intelligent insights.' ); ?>
                    </p>
                    <div class="hero-buttons">
                        <a href="book-demo.php" class="btn btn-primary">
                            <?php echo getSetting($platform_settings, 'cta_primary_text', 'Get Started'); ?>
                        </a>
                        <a href="#features" class="btn btn-outline">
                            <?php echo getSetting($platform_settings, 'cta_secondary_text', 'Explore Features'); ?>
                        </a>
                    </div>
                    <div class="trust-indicators">
                        <div class="trust-item">
                            <span>🔒</span>
                            <span><?php echo getSetting($platform_settings, 'security_text', 'Bank-Level Security'); ?></span>
                        </div>
                        <div class="trust-item">
                            <span>⚡</span>
                            <span><?php echo getSetting($platform_settings, 'performance_text', '99.9% Uptime'); ?></span>
                        </div>
                        <div class="trust-item">
                            <span>🎯</span>
                            <span><?php echo getSetting($platform_settings, 'support_text', '24/7 Support'); ?></span>
                        </div>
                    </div>
                </div>
                <div class="hero-image">
                    <img src="assets/images/widget/undraw_finance_m6vw.svg" alt="Finance Dashboard" style="max-width: 100%; height: auto; border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);">
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2><?php echo getSetting($platform_settings, 'features_title', 'Everything You Need to Scale'); ?></h2>
                <p><?php echo getSetting($platform_settings, 'features_subtitle', 'Comprehensive tools designed specifically for travel agencies to manage, grow, and optimize their business operations.'); ?></p>
            </div>
            <div class="features-grid">
                <?php
                $features = json_decode(getSetting($platform_settings, 'features_list', '[]'), true);
                if (empty($features)) {
                    // Optimized & Combined Features for MTravels
                    $features = [
                        ['icon' => '✈️', 'title' => 'Ticket Management', 'description' => 'Manage ticket bookings, reservations, refunds, and date changes with automated workflows and airline integration.'],
                        ['icon' => '⚖️', 'title' => 'Baggage & Ticket Weights', 'description' => 'Track baggage allowances, weight limits, and related pricing with profit monitoring.'],
                        ['icon' => '🏨', 'title' => 'Hotel Management', 'description' => 'Complete hotel booking and refund system with dynamic pricing, room management, and global integrations.'],
                        ['icon' => '🛂', 'title' => 'Visa Management', 'description' => 'Comprehensive visa applications, refunds, and multi-currency transactions with compliance tracking.'],
                        ['icon' => '🕋', 'title' => 'Umrah Services', 'description' => 'Specialized Umrah pilgrimage bookings, refunds, and package management with compliance handling.'],
                        ['icon' => '💰', 'title' => 'Financial Management', 'description' => 'Multi-currency accounting, expense tracking, salary processing, JV payments, and financial reporting with export support.'],
                        ['icon' => '📑', 'title' => 'Document & Maktob Management', 'description' => 'Manage official letters, agreements, and administrative paperwork with version tracking.'],
                        ['icon' => '👥', 'title' => 'Customer & Supplier Management', 'description' => 'Advanced CRM with booking history, preferences, and supplier coordination for smooth operations.'],
                        ['icon' => '💬', 'title' => 'Inter-Tenant Communication', 'description' => 'Real-time messaging and collaboration tools for tenant coordination and customer support.'],
                        ['icon' => '🏢', 'title' => 'Assets & Expense Management', 'description' => 'Track company assets, calculate depreciation, schedule maintenance, and manage categorized expenses.'],
                        ['icon' => '📊', 'title' => 'Analytics & Reporting', 'description' => 'Interactive dashboards, KPIs, compliance reports, activity logs, and strategic insights.'],
                        ['icon' => '🧾', 'title' => 'Invoice & Payment Processing', 'description' => 'Automated invoice generation, additional payments, multi-currency receipts, and digital delivery.'],
                        ['icon' => '👤', 'title' => 'User & Role Management', 'description' => 'Role-based access control with permissions, security, and activity logging.']
                    ];
                }
                

                foreach ($features as $feature) {
                    echo '<div class="feature-card">';
                    echo '<div class="feature-icon">' . htmlspecialchars($feature['icon'] ?? '🚀') . '</div>';
                    echo '<h3>' . htmlspecialchars($feature['title'] ?? 'Feature Title') . '</h3>';
                    echo '<p>' . htmlspecialchars($feature['description'] ?? 'Feature description') . '</p>';
                    echo '<a href="book-demo.php" class="feature-link">Try it now →</a>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">🏢</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_agencies', '10K+'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_agencies_label', 'Travel Agencies'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_bookings', '2M+'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_bookings_label', 'Bookings Processed'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_revenue', '$500M+'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_revenue_label', 'Revenue Managed'); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_uptime', '99.9%'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_uptime_label', 'Uptime Guaranteed'); ?></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <?php if (!empty($plans)): ?>
    <section class="pricing" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2><?php echo getSetting($platform_settings, 'pricing_title', 'Choose Your Plan'); ?></h2>
                <p><?php echo getSetting($platform_settings, 'pricing_subtitle', 'Select the perfect plan for your travel agency. All plans include our core features with different usage limits.'); ?></p>
            </div>
            <div class="pricing-grid">
                <?php foreach ($plans as $index => $plan): ?>
                <div class="pricing-card <?php echo $index === 1 ? 'popular' : ''; ?>">
                    <div class="pricing-name"><?php echo htmlspecialchars(formatFeatureName($plan['name'])); ?></div>
                    <div class="pricing-price"><?php echo formatCurrency($plan['price']); ?></div>
                    <div class="pricing-period"><?php echo htmlspecialchars(formatFeatureName('per_month')); ?></div>
                    <div class="pricing-features">
                        <?php
                        $planName = strtolower($plan['name']);

                        // Group features by category for all plans
                        $featureGroups = [
                            'ticket_management' => [
                                'title' => 'Ticket Management',
                                'features' => ['ticket_bookings', 'ticket_reservations', 'refunded_tickets', 'date_change_tickets', 'ticket_weights']
                            ],
                            'hotel_services' => [
                                'title' => 'Hotel Services',
                                'features' => ['hotel_bookings', 'hotel_refunds']
                            ],
                            'visa_services' => [
                                'title' => 'Visa Services',
                                'features' => ['visa_applications', 'visa_refunds', 'visa_transactions']
                            ],
                            'financial_management' => [
                                'title' => 'Financial Management',
                                'features' => ['debtors', 'creditors', 'sarafi', 'salary', 'additional_payments', 'jv_payments']
                            ],
                            'business_operations' => [
                                'title' => 'Business Operations',
                                'features' => ['manage_maktobs', 'assets', 'expense_management', 'customer_management', 'supplier_management']
                            ],
                            'communication' => [
                                'title' => 'Communication',
                                'features' => ['inter_tenant_chat']
                            ],
                            'reporting_analytics' => [
                                'title' => 'Reporting & Analytics',
                                'features' => ['business_analytics', 'reporting_system', 'activity_logging', 'dashboard_analytics', 'invoice_generation']
                            ],
                            'advanced_features' => [
                                'title' => 'Advanced Features',
                                'features' => ['user_management', 'financial_data_export']
                            ]
                        ];

                        // Display feature groups
                        $planFeatures = json_decode($plan['features'], true) ?? [];
                        foreach ($featureGroups as $groupKey => $group) {
                            $availableFeatures = array_intersect($group['features'], $planFeatures);
                            if (!empty($availableFeatures)) {
                                echo '<div class="feature-group">';
                                echo '<h5 class="feature-group-title">' . htmlspecialchars($group['title']) . '</h5>';
                                echo '<ul class="feature-group-list">';
                                foreach ($availableFeatures as $feature) {
                                    echo '<li class="feature-item">' . htmlspecialchars(formatFeatureName($feature)) . '</li>';
                                }
                                echo '</ul>';
                                echo '</div>';
                            }
                        }

                        // Always show user limit and trial info
                        echo '<div class="feature-group">';
                        echo '<h5 class="feature-group-title">Account & Support</h5>';
                        echo '<ul class="feature-group-list">';
                        echo '<li class="feature-highlight">Up to ' . htmlspecialchars($plan['max_users']) . ' users</li>';
                        echo '<li class="feature-highlight">' . htmlspecialchars($plan['trial_days']) . ' day free trial</li>';
                        echo '</ul>';
                        echo '</div>';
                        ?>
                    </div>
                    <a href="book-demo.php" class="btn btn-primary">Get Started</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="section-header">
                <h2><?php echo getSetting($platform_settings, 'contact_title', 'Get In Touch'); ?></h2>
                <p><?php echo getSetting($platform_settings, 'contact_subtitle', 'Ready to transform your travel business? Contact us today to learn more about MTravels.'); ?></p>
            </div>
            <div class="contact-content">
                <div class="contact-info">
                    <div class="contact-item">
                        <div class="contact-icon">📧</div>
                        <div class="contact-details">
                            <h4>Email Us</h4>
                            <p><?php echo getSetting($platform_settings, 'contact_email', 'allahdadmuhammadi01@gmail.com'); ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📞</div>
                        <div class="contact-details">
                            <h4>Call Us</h4>
                            <p><?php echo getSetting($platform_settings, 'support_phone', '+93780310431'); ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">📍</div>
                        <div class="contact-details">
                            <h4>Visit Us</h4>
                            <p><?php echo getSetting($platform_settings, 'contact_address', 'Kabul, Afghanistan'); ?></p>
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">🌐</div>
                        <div class="contact-details">
                            <h4>Website</h4>
                            <p><a href="<?php echo getSetting($platform_settings, 'website_url', 'https://mtravels.com'); ?>" target="_blank"><?php echo getSetting($platform_settings, 'website_url', 'https://mtravels.com'); ?></a></p>
                        </div>
                    </div>
                </div>
                <div class="contact-form">
                    <?php
                    // Display success/error messages
                    if (isset($_SESSION['contact_success'])) {
                        echo '<div class="alert alert-success" style="padding: 1rem; margin-bottom: 1rem; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 10px;">' . $_SESSION['contact_success'] . '</div>';
                        unset($_SESSION['contact_success']);
                    }
                    if (isset($_SESSION['contact_error'])) {
                        echo '<div class="alert alert-error" style="padding: 1rem; margin-bottom: 1rem; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 10px;">' . $_SESSION['contact_error'] . '</div>';
                        unset($_SESSION['contact_error']);
                    }
                    ?>
                    <form action="contact_handler.php" method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" name="name" placeholder="Your Name" required>
                            </div>
                            <div class="form-group">
                                <input type="email" name="email" placeholder="Your Email" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <input type="text" name="subject" placeholder="Subject" required>
                        </div>
                        <div class="form-group">
                            <textarea name="message" placeholder="Your Message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <?php if (!empty($testimonials)): ?>
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-header">
                <h2><?php echo getSetting($platform_settings, 'testimonials_title', 'What Our Customers Say'); ?></h2>
                <p><?php echo getSetting($platform_settings, 'testimonials_subtitle', 'Join thousands of satisfied travel agencies who have transformed their business with MTravels.'); ?></p>
            </div>
            <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-content">
                        "<?php echo htmlspecialchars($testimonial['testimonial']); ?>"
                    </div>
                    <div class="testimonial-author">
                        <?php if (!empty($testimonial['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($testimonial['photo']); ?>" alt="<?php echo htmlspecialchars($testimonial['name']); ?>" class="author-avatar">
                        <?php else: ?>
                        <div class="author-avatar" style="background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                            <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="author-info">
                            <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                            <div class="author-rating">
                                <?php
                                $rating = intval($testimonial['rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '⭐' : '☆';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2><?php echo getSetting($platform_settings, 'cta_title', 'Ready to Optimize Your Travel Operations?'); ?></h2>
            <p><?php echo getSetting($platform_settings, 'cta_subtitle', 'Join industry-leading travel agencies who have improved efficiency, reduced errors, and enhanced customer satisfaction with our comprehensive management platform.'); ?></p>
            <div class="cta-buttons">
                <a href="book-demo.php" class="btn btn-primary">
                    <?php echo getSetting($platform_settings, 'final_cta_primary', 'Get Started Today'); ?>
                </a>
                <a href="#contact" class="btn btn-outline">
                    <?php echo getSetting($platform_settings, 'final_cta_secondary', 'Contact Sales'); ?>
                </a>
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
                    <p style="color: var(--gray-300); margin-top: 1rem;">
                        <strong>Contact:</strong><br>
                        Email: <?php echo getSetting($platform_settings, 'contact_email', 'support@mtravels.com'); ?><br>
                        Phone: <?php echo getSetting($platform_settings, 'support_phone', '+93780310431'); ?><br>
                        Address: <?php echo getSetting($platform_settings, 'contact_address', 'Kabul, Afghanistan'); ?>
                    </p>
                </div>
                <div class="footer-section">
                    <h3><?php echo getSetting($platform_settings, 'footer_product_title', 'Product'); ?></h3>
                    <ul>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_features', 'Features'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_pricing', 'Pricing'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_integrations', 'Integrations'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_api', 'API Documentation'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_security', 'Security'); ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3><?php echo getSetting($platform_settings, 'footer_company_title', 'Company'); ?></h3>
                    <ul>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_about', 'About Us'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_careers', 'Careers'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_press', 'Press'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_blog', 'Blog'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_partners', 'Partners'); ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3><?php echo getSetting($platform_settings, 'footer_support_title', 'Support'); ?></h3>
                    <ul>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_help', 'Help Center'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_contact', 'Contact Support'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_status', 'System Status'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_community', 'Community'); ?></a></li>
                        <li><a href="#"><?php echo getSetting($platform_settings, 'footer_training', 'Training'); ?></a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?>. <?php echo getSetting($platform_settings, 'footer_copyright', 'All rights reserved. | Privacy Policy | Terms of Service'); ?></p>
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

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.feature-card, .stat-item').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Counter animation for stats
        function animateCounter(element, target, duration = 2000) {
            const start = 0;
            const increment = target / (duration / 16);
            let current = start;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }

                // Format numbers
                let displayValue = Math.floor(current);
                if (target >= 1000000) {
                    displayValue = (displayValue / 1000000).toFixed(1) + 'M';
                } else if (target >= 1000) {
                    displayValue = (displayValue / 1000).toFixed(0) + 'K';
                } else if (target < 100 && target % 1 !== 0) {
                    displayValue = current.toFixed(1);
                }

                // Special handling for percentage and currency
                if (element.textContent.includes('%')) {
                    element.textContent = displayValue + '%';
                } else if (element.textContent.includes('$')) {
                    element.textContent = '$' + displayValue;
                } else {
                    element.textContent = displayValue;
                }
            }, 16);
        }

        // Trigger counter animations when stats section is visible
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumbers = entry.target.querySelectorAll('.stat-number');
                    statNumbers.forEach((stat, index) => {
                        const targets = [<?php echo getSetting($platform_settings, 'stat_agencies_target', '10000'); ?>, <?php echo getSetting($platform_settings, 'stat_bookings_target', '2000000'); ?>, <?php echo getSetting($platform_settings, 'stat_revenue_target', '500000000'); ?>, <?php echo getSetting($platform_settings, 'stat_uptime_target', '99.9'); ?>];
                        setTimeout(() => {
                            animateCounter(stat, targets[index]);
                        }, index * 200);
                    });
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        const statsSection = document.querySelector('.stats');
        if (statsSection) {
            statsObserver.observe(statsSection);
        }

        // Add parallax effect to floating elements
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const parallax = document.querySelectorAll('.floating-element');

            parallax.forEach((element, index) => {
                const speed = 0.5 + (index * 0.1);
                element.style.transform = `translateY(${scrolled * speed}px)`;
            });
        });

        // Dynamic background animation
        function createParticle() {
            const particle = document.createElement('div');
            particle.style.cssText = `
                position: absolute;
                width: 4px;
                height: 4px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                pointer-events: none;
                animation: float 15s linear infinite;
            `;

            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';

            document.querySelector('.floating-elements').appendChild(particle);

            setTimeout(() => {
                particle.remove();
            }, 15000);
        }

        // Create particles periodically
        setInterval(createParticle, 3000);

        // Mobile menu functionality (for future mobile optimization)
        function toggleMobileMenu() {
            // Implementation for mobile menu toggle
        }
    </script>
</body>
</html>