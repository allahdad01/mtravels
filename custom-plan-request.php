<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/cache.php';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/landing-data.php';
require_once 'includes/theme-helper.php';
require_once 'includes/feature-selector.php';

$landingData = fetchLandingPageData($pdo);
$platform_settings = $landingData['settings'];
$success = $_GET['success'] ?? '';
$categories = getCustomFeatureCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build Your Custom Plan - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Design your own custom plan for your travel agency. Select only the features you need and get a personalized quote.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/custom-plan.css">
    <?php renderThemeStyles(); ?>
</head>
<body class="cp-page">
    <div class="animated-bg"></div>
    <div class="floating-elements">
        <div class="floating-element"></div>
        <div class="floating-element"></div>
        <div class="floating-element"></div>
    </div>

    <?php
    $nav_links = [
        ['href' => 'index.php', 'label' => 'Home'],
        ['href' => 'index.php#features', 'label' => 'Features'],
        ['href' => 'index.php#pricing', 'label' => 'Pricing'],
        ['href' => 'index.php#contact', 'label' => 'Contact']
    ];
    require_once 'includes/navbar.php';
    ?>

    <!-- Hero -->
    <section class="cp-hero">
        <div class="container">
            <div class="cp-hero-content">
                <h1>Build Your <span class="cp-gradient-text"><span>Custom Plan</span></span></h1>
                <p>Every travel agency is unique. Select only the features you need, tell us about your agency, and we'll create a personalized plan with a price that fits your budget.</p>
            </div>
        </div>
    </section>

    <?php if ($success === 'submitted'): ?>
    <section class="cp-success">
        <div class="container">
            <div class="cp-success-card">
                <div class="cp-success-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                </div>
                <h2>Request Submitted Successfully!</h2>
                <p>Thank you for your interest! Our team will review your custom plan request and contact you within 24-48 hours to discuss pricing and next steps.</p>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>
        </div>
    </section>
    <?php else: ?>

    <!-- Step Indicator -->
    <div class="cp-steps">
        <div class="container">
            <div class="cp-steps-bar">
                <div class="cp-step active">
                    <span class="cp-step-num">1</span>
                    <span class="cp-step-label">Select Features</span>
                </div>
                <div class="cp-step-connector"></div>
                <div class="cp-step">
                    <span class="cp-step-num">2</span>
                    <span class="cp-step-label">Your Details</span>
                </div>
                <div class="cp-step-connector"></div>
                <div class="cp-step">
                    <span class="cp-step-num">3</span>
                    <span class="cp-step-label">Get Quote</span>
                </div>
            </div>
        </div>
    </div>

    <form id="cp-form" method="POST" action="submit-custom-plan.php" class="cp-form">
        <!-- Step 1: Feature Selection -->
        <section class="cp-section" id="cp-step-1">
            <div class="container">
                <div class="cp-section-header">
                    <h2>Drag & Drop Your Features</h2>
                    <p>Click or drag features from the categories below to build your perfect plan.</p>
                    <div class="cp-actions">
                        <button type="button" class="cp-btn cp-btn-outline" id="cp-select-all">Select All</button>
                        <button type="button" class="cp-btn cp-btn-ghost" id="cp-clear-all">Clear All</button>
                    </div>
                </div>

                <div class="cp-builder">
                    <div class="cp-available">
                        <div class="cp-available-header">
                            <h3>Available Features</h3>
                            <span class="cp-badge">Click to add</span>
                        </div>
                        <div class="cp-available-list">
                            <?php echo renderFeatureSelector($categories); ?>
                        </div>
                    </div>

                    <div class="cp-selected" id="cp-selected-area">
                        <div class="cp-selected-header">
                            <h3>Your Custom Plan</h3>
                            <span class="cp-badge cp-badge-primary"><span id="cp-selected-count">0</span> features selected</span>
                        </div>
                        <div class="cp-selected-list" id="cp-selected-features">
                        </div>
                        <div class="cp-selected-empty" id="cp-selected-empty">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            <p>Drag features here or click to add</p>
                        </div>
                        <input type="hidden" name="selected_features" id="cp-selected-features-input" value="">
                    </div>
                </div>

                <div class="cp-nav-buttons">
                    <button type="button" class="cp-btn cp-btn-primary cp-btn-next" onclick="goToStep(2)">Continue to Details <span>&rarr;</span></button>
                </div>
            </div>
        </section>

        <!-- Step 2: Contact Details -->
        <section class="cp-section cp-section-hidden" id="cp-step-2">
            <div class="container">
                <div class="cp-section-header">
                    <h2>Tell Us About Yourself</h2>
                    <p>Provide your contact details so our team can reach out with a personalized quote.</p>
                </div>

                <div class="cp-form-card">
                    <div class="cp-form-grid">
                        <div class="cp-field">
                            <label for="contact_name">Full Name <span class="cp-req">*</span></label>
                            <input type="text" id="contact_name" name="contact_name" required placeholder="John Doe" maxlength="100">
                        </div>
                        <div class="cp-field">
                            <label for="contact_email">Email Address <span class="cp-req">*</span></label>
                            <input type="email" id="contact_email" name="contact_email" required placeholder="john@agency.com">
                        </div>
                        <div class="cp-field">
                            <label for="contact_phone">Phone Number <span class="cp-req">*</span></label>
                            <input type="tel" id="contact_phone" name="contact_phone" required placeholder="+93 700 000 000">
                        </div>
                        <div class="cp-field">
                            <label for="agency_name">Agency Name</label>
                            <input type="text" id="agency_name" name="agency_name" placeholder="Your Travel Agency" maxlength="255">
                        </div>
                    </div>

                    <div class="cp-field">
                        <label for="max_users">Expected Number of Users</label>
                        <input type="number" id="max_users" name="max_users" min="1" value="1" placeholder="5">
                    </div>

                    <div class="cp-field">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" name="notes" rows="4" placeholder="Tell us more about your requirements, expected volume, or any specific needs..." maxlength="2000"></textarea>
                    </div>

                    <div class="cp-nav-buttons">
                        <button type="button" class="cp-btn cp-btn-ghost" onclick="goToStep(1)"><span>&larr;</span> Back to Features</button>
                        <button type="submit" class="cp-btn cp-btn-primary cp-btn-submit">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            Submit Request
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </form>

    <?php endif; ?>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>

    <script>
    function goToStep(step) {
        document.querySelectorAll('.cp-section').forEach(s => s.classList.add('cp-section-hidden'));
        document.getElementById('cp-step-' + step)?.classList.remove('cp-section-hidden');

        document.querySelectorAll('.cp-step').forEach((s, i) => {
            s.classList.toggle('active', i < step);
            s.classList.toggle('current', i === step - 1);
        });
        window.scrollTo({ top: document.querySelector('.cp-steps').offsetTop - 100, behavior: 'smooth' });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.cp-step').forEach((s, i) => {
            if (i === 0) { s.classList.add('active', 'current'); }
        });
    });
    </script>

    <?php echo renderFeatureSelectorScript(); ?>
    <?php renderThemeScript(); ?>
</body>
</html>
