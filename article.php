<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/theme-helper.php';
require_once 'includes/helpers.php';

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
        return [];
    }
}

// Function to fetch a single blog post by slug
function getBlogPostBySlug($pdo, $slug) {
    $cache_key = getCacheKey('blog_post_' . $slug);

    if ($cached = getCachedData($cache_key)) {
        return $cached;
    }

    try {
        $stmt = $pdo->prepare("SELECT `id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at` FROM `blog_posts` WHERE `slug` = ? AND `status` = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        $blog_post = $stmt->fetch(PDO::FETCH_ASSOC);

        setCachedData($cache_key, $blog_post);
        return $blog_post;
    } catch (PDOException $e) {
        return null;
    }
}

// Helper function to format date
function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

// Fetch data
$platform_settings = getPlatformSettings($pdo);

// Get the slug from the URL
$slug = $_GET['slug'] ?? null;
$blog_post = null;

if ($slug) {
    $blog_post = getBlogPostBySlug($pdo, $slug);
}

if (!$blog_post) {
    // Redirect to blog.php if the post is not found
    header("Location: blog.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($blog_post['title']); ?> - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($blog_post['excerpt']); ?>">
    <!-- Favicon -->
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <?php renderThemeStyles(); ?>
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
            color: var(--text-primary);
            background: var(--bg-primary);
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Theme Toggle Button */
        .theme-toggle {
            background: transparent;
            border: 2px solid var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .theme-toggle:hover {
            background: rgba(64, 153, 255, 0.1);
            transform: scale(1.1);
        }

        .theme-toggle:active {
            transform: scale(0.95);
        }


        /* Article Header */
        .article-header {
            padding: 12rem 0 4rem;
            position: relative;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            text-align: center;
        }

        .article-header h1 {
            font-size: 3rem;
            font-weight: 900;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .article-meta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            margin-top: 1rem;
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .article-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Article Content */
        .article-content-section {
            padding: 4rem 0;
            background: var(--white);
        }

        .article-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .article-featured-image {
            width: 100%;
            height: 400px;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .article-featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .article-content {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--text-secondary);
            margin-bottom: 2rem;
        }

        .article-content h2 {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            margin: 2rem 0 1rem;
        }

        .article-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 1.5rem 0 1rem;
        }

        .article-content p {
            margin-bottom: 1.5rem;
        }

        .article-content ul,
        .article-content ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
        }

        .article-content li {
            margin-bottom: 0.5rem;
        }

        .article-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .article-tag {
            background: var(--bg-secondary);
            color: var(--primary);
            padding: 0.25rem 0.75rem;
            border: 1px solid rgba(64, 153, 255, 0.2);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Back to Blog Button */
        .back-to-blog {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-to-blog:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Dark Mode Styles */
        html.dark-mode .article-header {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }

        html.dark-mode .article-content-section {
            background: var(--bg-primary);
        }

        html.dark-mode .article-container {
            background: var(--bg-surface);
        }

        html.dark-mode .back-to-blog {
            background: var(--primary);
            color: var(--white);
        }

        html.dark-mode .back-to-blog:hover {
            background: var(--primary-dark);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .article-header h1 {
                font-size: 2rem;
            }

            .article-header {
                padding: 8rem 0 3rem;
            }

            .article-featured-image {
                height: 250px;
            }

            .article-content-section {
                padding: 2rem 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php require_once 'includes/navbar.php'; ?>

    <!-- Article Header -->
    <header class="article-header">
        <div class="container">
            <h1><?php echo htmlspecialchars($blog_post['title']); ?></h1>
            <div class="article-meta">
                <div class="article-meta-item">
                    👤 <?php echo htmlspecialchars($blog_post['author'] ?? 'MTravels Team'); ?>
                </div>
                <div class="article-meta-item">
                    📅 <?php echo formatDate($blog_post['created_at']); ?>
                </div>
                <?php if (!empty($blog_post['category'])): ?>
                    <div class="article-meta-item">
                        🏷️ <?php echo htmlspecialchars($blog_post['category']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Article Content -->
    <section class="article-content-section">
        <div class="container">
            <div class="article-container">
                <?php if (!empty($blog_post['featured_image'])): ?>
                    <div class="article-featured-image">
                        <img src=".<?php echo htmlspecialchars($blog_post['featured_image']); ?>" alt="<?php echo htmlspecialchars($blog_post['title']); ?>">
                    </div>
                <?php endif; ?>

                <div class="article-content">
                    <?php echo $blog_post['content']; ?>
                </div>

                <div class="article-tags">
                    <span class="article-tag">Travel</span>
                    <span class="article-tag">Technology</span>
                </div>

                <a href="blog.php" class="back-to-blog">← Back to Blog</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>

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
    </script>
    <script>
    // Mobile menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.querySelector('.nav-menu');

        if (hamburger && navMenu) {
            hamburger.addEventListener('click', function() {
                navMenu.classList.toggle('open');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!hamburger.contains(event.target) && !navMenu.contains(event.target)) {
                    navMenu.classList.remove('open');
                }
            });
        }
    });
</script>
    <?php renderThemeScript(); ?>
</body>
</html>