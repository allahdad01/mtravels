
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
$plans = $landingData['plans'];
$destinations = $landingData['destinations'];
$testimonials = $landingData['testimonials'];
$deals = $landingData['deals'];
$blog_posts = $landingData['blog_posts'];
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
    <link rel="stylesheet" href="assets/css/index.css">
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
        ['href' => '#features', 'label' => 'Features'],
        ['href' => '#pricing', 'label' => 'Pricing'],
        ['href' => '#testimonials', 'label' => 'Reviews'],
        ['href' => '#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php'; 
    ?>

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
                    <div class="laptop-mockup">
                        <div class="laptop-screen">
                            <img src="uploads/hero_image/dashboard.webp" alt="MTravels Dashboard">
                        </div>
                        <div class="laptop-base">
                            <div class="laptop-notch"></div>
                        </div>
                    </div>
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
            <?php echo renderFeatureSplitSection(getFeaturesList($platform_settings)); ?>
            <div style="text-align: center; margin-top: 3rem;">
                <a href="features.php" class="btn btn-outline" style="font-size: 1.05rem; padding: 0.9rem 2.5rem;">
                    Explore All Features in Detail
                </a>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="section-header">
                <h2><?php echo getSetting($platform_settings, 'stats_title', 'Trusted by Thousands'); ?></h2>
                <p><?php echo getSetting($platform_settings, 'stats_subtitle', 'Our platform delivers measurable results for travel agencies worldwide.'); ?></p>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg>
                    </div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_agencies', '10K+'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_agencies_label', 'Travel Agencies'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
                    </div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_bookings', '2M+'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_bookings_label', 'Bookings Processed'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </div>
                    <div class="stat-number"><?php echo getSetting($platform_settings, 'stat_revenue', '$500M+'); ?></div>
                    <div class="stat-label"><?php echo getSetting($platform_settings, 'stat_revenue_label', 'Revenue Managed'); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                    </div>
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
                <div class="pricing-card <?php echo strtolower($plan['name']) === 'enterprise' ? 'popular' : ''; ?>">
                    <div class="pricing-badge">Most Popular</div>
                    <div class="pricing-name"><?php echo htmlspecialchars(formatFeatureName($plan['name'])); ?></div>
                    <div class="pricing-price"><?php echo formatCurrency($plan['price']); ?></div>
                    <div class="pricing-period"><?php echo htmlspecialchars(formatFeatureName('per_month')); ?></div>
                    <div class="pricing-features">
                        <?php echo renderPricingCardFeatures($plan, $index, $plans); ?>
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
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        </div>
                        <div class="contact-card-body">
                            <h4>Email Us</h4>
                            <p><?php echo getSetting($platform_settings, 'contact_email', 'allahdadmuhammadi01@gmail.com'); ?></p>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        </div>
                        <div class="contact-card-body">
                            <h4>Call Us</h4>
                            <p><?php echo getSetting($platform_settings, 'support_phone', '+93780310431'); ?></p>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        </div>
                        <div class="contact-card-body">
                            <h4>Visit Us</h4>
                            <p><?php echo getSetting($platform_settings, 'contact_address', 'Kabul, Afghanistan'); ?></p>
                        </div>
                    </div>
                    <div class="contact-card">
                        <div class="contact-card-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" x2="22" y1="12" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </div>
                        <div class="contact-card-body">
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
                         <!-- CSRF Token Protection -->
                         <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? bin2hex(random_bytes(32))); ?>">
                         
                         <div class="form-row">
                             <div class="form-group">
                                 <input type="text" name="name" placeholder="Your Name" required maxlength="100">
                             </div>
                             <div class="form-group">
                                 <input type="email" name="email" placeholder="Your Email" required>
                             </div>
                         </div>
                         <div class="form-group">
                             <input type="text" name="subject" placeholder="Subject" required maxlength="100">
                         </div>
                         <div class="form-group">
                             <textarea name="message" placeholder="Your Message" rows="5" required maxlength="1000"></textarea>
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

            <!-- Auto-play indicator -->
            <div class="auto-play-indicator">
                <span>Auto-play</span>
                <div class="auto-play-progress">
                    <div class="auto-play-bar" id="autoPlayBar"></div>
                </div>
            </div>

            <div class="testimonials-slider">
                <div class="testimonials-track" id="testimonialsTrack">
                    <?php foreach ($testimonials as $index => $testimonial): ?>
                    <div class="testimonial-slide">
                        <div class="testimonial-card">
                            <div class="testimonial-content">
                                "<?php echo htmlspecialchars($testimonial['testimonial']); ?>"
                            </div>
                            <div class="testimonial-author">
                                <?php if (!empty($testimonial['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($testimonial['photo']); ?>" alt="<?php echo htmlspecialchars($testimonial['name']); ?>" class="author-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="author-avatar" style="background: var(--primary); display: none; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                                </div>
                                <?php else: ?>
                                <div class="author-avatar" style="background: var(--primary); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($testimonial['name'], 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div class="author-info">
                                    <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                                    <?php if (!empty($testimonial['position'])): ?>
                                    <span class="author-position"><?php echo htmlspecialchars($testimonial['position']); ?></span>
                                    <?php endif; ?>
                                    <div class="author-rating" data-rating="<?php echo intval($testimonial['rating']); ?>">
                                        <?php $r = intval($testimonial['rating']); for ($i = 1; $i <= 5; $i++): ?>
                                        <svg class="star<?php echo $i <= $r ? ' filled' : ''; ?>" width="14" height="14" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                        </svg>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Slider Navigation -->
            <div class="slider-nav">
                <button class="slider-btn" id="prevBtn" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="slider-dots" id="sliderDots"></div>
                <button class="slider-btn" id="nextBtn">
                    <i class="fas fa-chevron-right"></i>
                </button>
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

        // Features timeline animation observer - individual item animation
        const timelineObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate');
                    timelineObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        // Observe each timeline item individually
        document.querySelectorAll('.timeline-item').forEach(item => {
            timelineObserver.observe(item);
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

        // Mobile menu functionality
        function toggleMobileMenu() {
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

                // Close menu when clicking on a link
                navMenu.addEventListener('click', function(event) {
                    if (event.target.tagName === 'A') {
                        navMenu.classList.remove('open');
                    }
                });
            }
        }

        // Feature sticky scroll with screenshot carousel
        class FeatureStickyScroll {
            constructor() {
                this.wrap = document.querySelector('.features-scroll-wrap');
                if (!this.wrap) return;

                this.featureImages = JSON.parse(this.wrap.dataset.featureImages);
                this.items = this.wrap.querySelectorAll('.ft-item');
                this.stage = this.wrap.querySelector('.fv-images-stage');
                this.dotsWrap = this.wrap.querySelector('.fv-images-dots');
                this.btnPrev = this.wrap.querySelector('.fv-img-prev');
                this.btnNext = this.wrap.querySelector('.fv-img-next');
                this.imagesWrap = this.wrap.querySelector('.fv-images-wrap');
                this.curNum = this.wrap.querySelector('.fv-curnum');
                this.progressFill = this.wrap.querySelector('.fv-progress-fill');
                this.total = this.featureImages.length;
                this.currentFeature = 0;
                this.currentImg = 0;
                this.isTransitioning = false;

                this.init();
            }

            init() {
                this.renderFeature(0, false);

                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const idx = parseInt(entry.target.dataset.index);
                            if (idx !== this.currentFeature) {
                                this.goToFeature(idx);
                            }
                        }
                    });
                }, { threshold: 0.5 });

                this.items.forEach(item => observer.observe(item));

                window.addEventListener('scroll', () => this.updateProgress());
                this.updateProgress();

                this.btnPrev.addEventListener('click', () => this.prevImage());
                this.btnNext.addEventListener('click', () => this.nextImage());
                this.dotsWrap.addEventListener('click', (e) => {
                    const btn = e.target.closest('button');
                    if (btn) {
                        this.goToImage(parseInt(btn.dataset.idx));
                    }
                });
                this.stage.addEventListener('click', (e) => {
                    const img = e.target.closest('img');
                    if (img) {
                        const idx = parseInt(img.dataset.idx);
                        this.openLightbox(idx);
                    }
                });
            }

            openLightbox(imgIdx) {
                const existing = document.querySelector('.fv-lightbox');
                if (existing) existing.remove();

                this._lightboxImages = this.featureImages[this.currentFeature];
                if (!this._lightboxImages || this._lightboxImages.length === 0) return;
                this._lightboxIndex = imgIdx;

                const overlay = document.createElement('div');
                overlay.className = 'fv-lightbox';
                overlay.innerHTML =
                    '<div class="fv-lightbox-backdrop"></div>' +
                    '<div class="fv-lightbox-body">' +
                        '<button class="fv-lightbox-prev" type="button" aria-label="Previous"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg></button>' +
                        '<div class="fv-lightbox-content">' +
                            '<button class="fv-lightbox-close" type="button" aria-label="Close">&times;</button>' +
                            '<div class="fv-lightbox-img-wrap"><img src="" alt=""></div>' +
                            '<div class="fv-lightbox-counter"></div>' +
                        '</div>' +
                        '<button class="fv-lightbox-next" type="button" aria-label="Next"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg></button>' +
                    '</div>';
                document.body.appendChild(overlay);

                this._updateLightboxImage();

                requestAnimationFrame(() => overlay.classList.add('open'));

                const closeHandler = (e) => {
                    if (e.target === overlay || e.target.closest('.fv-lightbox-backdrop') || e.target.closest('.fv-lightbox-close')) {
                        this.closeLightbox();
                    }
                };
                overlay.addEventListener('click', closeHandler);
                overlay.querySelector('.fv-lightbox-prev').addEventListener('click', (e) => { e.stopPropagation(); this.lightboxPrev(); });
                overlay.querySelector('.fv-lightbox-next').addEventListener('click', (e) => { e.stopPropagation(); this.lightboxNext(); });

                this._lightboxKeyHandler = (e) => {
                    if (e.key === 'Escape') this.closeLightbox();
                    if (e.key === 'ArrowLeft') this.lightboxPrev();
                    if (e.key === 'ArrowRight') this.lightboxNext();
                };
                document.addEventListener('keydown', this._lightboxKeyHandler);
            }

            _updateLightboxImage() {
                const overlay = document.querySelector('.fv-lightbox');
                if (!overlay) return;
                const img = overlay.querySelector('.fv-lightbox-img-wrap img');
                const counter = overlay.querySelector('.fv-lightbox-counter');
                const prev = overlay.querySelector('.fv-lightbox-prev');
                const next = overlay.querySelector('.fv-lightbox-next');
                const total = this._lightboxImages.length;

                img.src = this._lightboxImages[this._lightboxIndex];
                counter.textContent = (this._lightboxIndex + 1) + ' / ' + total;
                prev.style.display = total > 1 ? '' : 'none';
                next.style.display = total > 1 ? '' : 'none';
                prev.classList.toggle('edge', this._lightboxIndex === 0);
                next.classList.toggle('edge', this._lightboxIndex === total - 1);
            }

            lightboxPrev() {
                if (this._lightboxIndex > 0) {
                    this._lightboxIndex--;
                    this._updateLightboxImage();
                }
            }

            lightboxNext() {
                if (this._lightboxIndex < this._lightboxImages.length - 1) {
                    this._lightboxIndex++;
                    this._updateLightboxImage();
                }
            }

            closeLightbox() {
                const overlay = document.querySelector('.fv-lightbox');
                if (!overlay) return;
                overlay.classList.remove('open');
                setTimeout(() => overlay.remove(), 300);
                if (this._lightboxKeyHandler) {
                    document.removeEventListener('keydown', this._lightboxKeyHandler);
                    this._lightboxKeyHandler = null;
                }
                this._lightboxImages = null;
            }

            renderFeature(featureIdx, animate) {
                const images = this.featureImages[featureIdx];
                this.currentImg = 0;

                if (!images || images.length === 0) {
                    this.stage.innerHTML = '<div style="padding:3rem;text-align:center;color:var(--gray-400);width:100%;flex-shrink:0;">No screenshot available</div>';
                    this.dotsWrap.innerHTML = '';
                    this.btnPrev.classList.add('edge');
                    this.btnNext.classList.add('edge');
                    return;
                }

                if (animate) {
                    this.imagesWrap.classList.add('flipping');
                    setTimeout(() => {
                        this.buildCarousel(images);
                        this.imagesWrap.classList.remove('flipping');
                    }, 250);
                } else {
                    this.buildCarousel(images);
                }
            }

            buildCarousel(images) {
                this.stage.innerHTML = images.map((src, i) =>
                    `<img src="${src}" alt="" data-idx="${i}" loading="lazy">`
                ).join('');

                if (images.length > 1) {
                    this.dotsWrap.innerHTML = images.map((_, i) =>
                        `<button class="${i === 0 ? 'active' : ''}" data-idx="${i}"></button>`
                    ).join('');
                    this.btnPrev.classList.remove('edge');
                    this.btnNext.classList.remove('edge');
                } else {
                    this.dotsWrap.innerHTML = '';
                    this.btnPrev.classList.add('edge');
                    this.btnNext.classList.add('edge');
                }

                this.stage.style.transform = 'translateX(0)';
            }

            goToFeature(index) {
                if (index === this.currentFeature || this.isTransitioning) return;
                this.isTransitioning = true;
                this.currentFeature = index;
                this.currentImg = 0;

                this.items.forEach((item, i) => item.classList.toggle('active', i === index));
                this.curNum.textContent = String(index + 1).padStart(2, '0');

                this.renderFeature(index, true);
                setTimeout(() => { this.isTransitioning = false; }, 350);
            }

            goToImage(index) {
                const imgs = this.stage.children;
                if (!imgs.length || index < 0 || index >= imgs.length || index === this.currentImg) return;
                this.currentImg = index;
                this.stage.style.transform = 'translateX(-' + (index * 100) + '%)';

                const dots = this.dotsWrap.children;
                for (let i = 0; i < dots.length; i++) {
                    dots[i].classList.toggle('active', i === index);
                }

                this.btnPrev.classList.toggle('edge', index === 0);
                this.btnNext.classList.toggle('edge', index === imgs.length - 1);
            }

            prevImage() {
                if (this.currentImg > 0) this.goToImage(this.currentImg - 1);
            }

            nextImage() {
                const imgs = this.stage.children;
                if (this.currentImg < imgs.length - 1) this.goToImage(this.currentImg + 1);
            }

            updateProgress() {
                const rect = this.wrap.getBoundingClientRect();
                const scrollable = this.wrap.scrollHeight - window.innerHeight;
                if (scrollable <= 0) return;
                const scrolled = window.innerHeight - rect.top;
                const pct = Math.max(0, Math.min(100, (scrolled / scrollable) * 100));
                if (this.progressFill) this.progressFill.style.height = pct + '%';
            }
        }

        // Testimonial Slider Functionality
        class TestimonialSlider {
            constructor() {
                this.track = document.getElementById('testimonialsTrack');
                this.slides = document.querySelectorAll('.testimonial-slide');
                this.prevBtn = document.getElementById('prevBtn');
                this.nextBtn = document.getElementById('nextBtn');
                this.dotsContainer = document.getElementById('sliderDots');
                this.autoPlayBar = document.getElementById('autoPlayBar');

                this.currentIndex = 0;
                this.slidesPerView = 3;
                this.totalSlides = this.slides.length;
                this.autoPlayInterval = null;
                this.autoPlayDuration = 5000; // 5 seconds

                this.init();
            }

            init() {
                this.updateMaxIndex();
                this.createDots();
                this.updateButtons();
                this.updateDots();
                this.startAutoPlay();

                this.prevBtn.addEventListener('click', () => this.prev());
                this.nextBtn.addEventListener('click', () => this.next());

                // Pause auto-play on hover
                document.querySelector('.testimonials-slider').addEventListener('mouseenter', () => this.pauseAutoPlay());
                document.querySelector('.testimonials-slider').addEventListener('mouseleave', () => this.startAutoPlay());

                // Handle window resize
                window.addEventListener('resize', () => this.handleResize());
            }

            createDots() {
                // Clear existing dots
                this.dotsContainer.innerHTML = '';

                // Calculate total dots based on slides per view
                // For 6 slides with 3 per view, we need 4 dots (positions 0,1,2,3)
                const totalDots = Math.max(1, this.totalSlides - this.slidesPerView + 1);
                for (let i = 0; i < totalDots; i++) {
                    const dot = document.createElement('div');
                    dot.className = 'slider-dot';
                    dot.addEventListener('click', () => this.goToSlide(i));
                    this.dotsContainer.appendChild(dot);
                }
                this.dots = document.querySelectorAll('.slider-dot');
            }

            updateButtons() {
                this.prevBtn.disabled = this.currentIndex === 0;
                this.nextBtn.disabled = this.currentIndex >= this.totalSlides - this.slidesPerView;
            }

            updateDots() {
                this.dots.forEach((dot, index) => {
                    dot.classList.toggle('active', index === this.currentIndex);
                });
            }

            goToSlide(index) {
                this.currentIndex = Math.max(0, Math.min(index, this.totalSlides - this.slidesPerView));
                this.updateSlider();
                this.resetAutoPlay();
            }

            updateMaxIndex() {
                this.maxIndex = Math.max(0, this.totalSlides - this.slidesPerView);
            }

            prev() {
                if (this.currentIndex > 0) {
                    this.currentIndex--;
                    this.updateSlider();
                    this.resetAutoPlay();
                }
            }

            next() {
                if (this.currentIndex < this.totalSlides - this.slidesPerView) {
                    this.currentIndex++;
                    this.updateSlider();
                    this.resetAutoPlay();
                } else {
                    // Loop back to start
                    this.currentIndex = 0;
                    this.updateSlider();
                    this.resetAutoPlay();
                }
            }

            updateButtons() {
                const maxIndex = this.totalSlides - this.slidesPerView;
                this.prevBtn.disabled = this.currentIndex === 0;
                this.nextBtn.disabled = this.currentIndex >= maxIndex;
            }

            updateSlider() {
                const translateX = -this.currentIndex * (100 / this.slidesPerView);
                this.track.style.transform = `translateX(${translateX}%)`;
                this.updateButtons();
                this.updateDots();
            }

            startAutoPlay() {
                this.stopAutoPlay();
                this.autoPlayInterval = setInterval(() => {
                    this.animateProgressBar();
                    setTimeout(() => {
                        this.next();
                    }, 100); // Small delay to show progress bar completion
                }, this.autoPlayDuration);
            }

            pauseAutoPlay() {
                this.stopAutoPlay();
                this.resetProgressBar();
            }

            stopAutoPlay() {
                if (this.autoPlayInterval) {
                    clearInterval(this.autoPlayInterval);
                    this.autoPlayInterval = null;
                }
            }

            resetAutoPlay() {
                this.stopAutoPlay();
                this.resetProgressBar();
                this.startAutoPlay();
            }

            animateProgressBar() {
                this.autoPlayBar.style.width = '0%';
                this.autoPlayBar.style.transition = 'none';

                setTimeout(() => {
                    this.autoPlayBar.style.transition = `width ${this.autoPlayDuration}ms linear`;
                    this.autoPlayBar.style.width = '100%';
                }, 10);
            }

            resetProgressBar() {
                this.autoPlayBar.style.width = '0%';
                this.autoPlayBar.style.transition = 'none';
            }

            handleResize() {
                // Recalculate slides per view on resize if needed
                const newSlidesPerView = window.innerWidth < 768 ? 1 : 3;
                if (newSlidesPerView !== this.slidesPerView) {
                    this.slidesPerView = newSlidesPerView;
                    this.updateMaxIndex();
                    this.createDots(); // Recreate dots with new calculation
                    this.currentIndex = Math.min(this.currentIndex, this.maxIndex);
                    this.updateSlider();
                }
            }
        }

        // Initialize feature split slider, testimonial slider, and mobile menu
        document.addEventListener('DOMContentLoaded', function() {
            if (document.querySelector('.features-scroll-wrap')) {
                new FeatureStickyScroll();
            }
            if (document.querySelector('.testimonials-slider')) {
                new TestimonialSlider();
            }
            toggleMobileMenu();
        });
    </script>
    <?php renderThemeScript(); ?>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>

