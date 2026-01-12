<?php
session_start();

// Database connection and security
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/theme-helper.php';

// Handle form submission
if ($_POST) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $company_size = $_POST['company_size'] ?? '';
    $preferred_date = $_POST['preferred_date'] ?? '';
    $preferred_time = $_POST['preferred_time'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    // Basic validation
    if (empty($name) || empty($email) || empty($company)) {
        $_SESSION['demo_error'] = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['demo_error'] = 'Please enter a valid email address.';
    } else {
        try {
            // Insert demo request into database
            $stmt = $pdo->prepare("INSERT INTO demo_requests (name, email, company, phone, company_size, preferred_date, preferred_time, message, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $company, $phone, $company_size, $preferred_date, $preferred_time, $message]);
            
            $_SESSION['demo_success'] = 'Thank you! Your demo request has been submitted successfully. We will contact you within 24 hours to schedule your personalized demo.';
            
            // Clear form data
            unset($_POST);
        } catch (PDOException $e) {
            error_log("Demo request error: " . $e->getMessage());
            $_SESSION['demo_error'] = 'There was an error submitting your request. Please try again.';
        }
    }
}

// Get platform settings for branding
try {
    $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
    $stmt->execute();
    $platform_settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $platform_settings[$row['key']] = $row['value'];
    }
} catch (PDOException $e) {
    $platform_settings = [];
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Demo - <?php echo getSetting($platform_settings, 'platform_name', 'MTravels'); ?></title>
    <meta name="description" content="Schedule a personalized demo of MTravels - the most advanced travel agency management platform.">
    <link rel="icon" href="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'default-logo.png') ?>" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            /* Theme variables */
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --bg-surface: #ffffff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background: var(--bg-primary);
            min-height: 100vh;
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
            opacity: 0.05;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        .header {
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

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
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
            color: var(--primary);
        }

        .back-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: var(--primary-dark);
        }

        /* Hero Section */
        .hero {
            padding: 10rem 0 2rem;
            background: var(--bg-primary);
            text-align: center;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 1rem;
            line-height: 1.2;
            position: relative;
        }

        .hero h1::after {
            content: '';
            display: block;
            width: 120px;
            height: 6px;
            background: linear-gradient(#4099ff, #2ed8b6);
            border-radius: 50px;
            margin: 0.5rem auto 0;
            animation: expandWidth 2s ease-out 0.5s both;
        }

        @keyframes expandWidth {
            from { width: 0; }
            to { width: 120px; }
        }

        .hero p {
            font-size: 1.3rem;
            color: var(--text-secondary);
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
            background: var(--bg-primary);
        }

        .demo-container {
            display: grid;
            grid-template-columns: 0.7fr 1.3fr;
            gap: 0;
            align-items: stretch;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background: var(--bg-surface);
        }

        .demo-info {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
            padding: 2rem;
            border-radius: 0 0 70px 0;
        }

        .demo-info h1 {
            color: white;
        }

        .demo-info p {
            color: white;
        }

        .demo-info .benefits-list li {
            color: white;
        }

        .demo-info .stat-number {
            color: white;
        }

        .demo-info .stat-label {
            color: white;
        }

        .demo-info .stat-item {
            background: rgba(255, 255, 255, 0.1);
        }

        .demo-info h1 {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-primary);
            margin-bottom: 2rem;
            line-height: 1.2;
        }

        .demo-info p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.2;
        }

        .benefits-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .benefits-list li {
            display: flex;
            align-items: center;
            gap: 2rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .benefits-list li::before {
            content: '✓';
            background: var(--success);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            flex-shrink: 0;
        }

        .demo-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border: 1px solid rgba(64, 153, 255, 0.2);
            border-radius: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 600;
        }

        /* Demo Form */
        .demo-form {
            background: var(--bg-surface);
            padding: 3rem;
            border-radius: 0;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .form-header p {
            color: var(--text-secondary);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .required {
            color: var(--danger);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--bg-secondary);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-surface);
            color: var(--text-primary);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(64, 153, 255, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4099ff 0%, #2ed8b6 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(64, 153, 255, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Footer */
        .footer {
            background: var(--bg-secondary);
            color: var(--text-primary);
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
            background: linear-gradient(135deg, #4099ff, #2ed8b6);
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
            color: var(--text-primary);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section li {
            margin-bottom: 0.8rem;
        }

        .footer-section a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section a:hover {
            color: var(--primary);
        }

        .footer p {
            color: var(--text-secondary) !important;
        }

        .footer-bottom {
            padding-top: 3rem;
            border-top: 1px solid var(--primary);
            text-align: center;
            color: var(--text-secondary);
        }

        /* Dark Mode Support */
        html.dark-mode .header {
            background: rgba(30, 41, 59, 0.9);
        }

        html.dark-mode .back-link {
            color: #cbd5e1;
        }

        html.dark-mode .back-link:hover {
            color: #4099ff;
        }

        html.dark-mode .theme-toggle {
            border-color: #4099ff;
            color: #4099ff;
        }

        html.dark-mode .theme-toggle:hover {
            background: rgba(64, 153, 255, 0.2);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                left: 20px;
                right: 20px;
                padding: 1rem;
            }

            .logo-text {
                font-size: 1.5rem;
            }

            .back-link {
                font-size: 0.9rem;
            }

            .demo-container {
                grid-template-columns: 1fr;
                gap: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .demo-info,
            .demo-form {
                padding: 2rem;
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            }

            .demo-info h1 {
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .demo-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg"></div>

    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <img src="uploads/logo/<?= htmlspecialchars(getSetting($platform_settings, 'platform_logo') ?? 'logo.png') ?>" alt="Logo">
                    <span class="logo-text"><?= htmlspecialchars(getSetting($platform_settings, 'platform_name') ?? 'MTravels') ?></span>
                </a>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                        <span class="theme-icon">🌙</span>
                    </button>
                    <a href="index.php" class="back-link">
                        ← Back to Home
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Book a Demo</h1>
            <p>We look forward to showing you the benefits of MTravels</p>
        </div>
    </section>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <div class="demo-container">
                <!-- Demo Information -->
                <div class="demo-info">
                    <h1>See MTravels in Action</h1>
                    <p>Schedule a personalized demo and discover how MTravels can transform your travel agency operations. Our experts will show you exactly how our platform can streamline your workflows and boost your business growth.</p>
                    
                    <ul class="benefits-list">
                        <li>Personalized 30-minute demo session</li>
                        <li>See features relevant to your business</li>
                        <li>Get answers to your specific questions</li>
                        <li>Learn about pricing and implementation</li>
                        <li>No commitment required</li>
                    </ul>

                    <div class="demo-stats">
                        <div class="stat-item">
                            <div class="stat-number">10K+</div>
                            <div class="stat-label">Travel Agencies</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">2M+</div>
                            <div class="stat-label">Bookings Processed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">99.9%</div>
                            <div class="stat-label">Uptime</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support</div>
                        </div>
                    </div>
                </div>

                <!-- Demo Form -->
                <div class="demo-form">
                    <div class="form-header">
                        <h2>Book Your Demo</h2>
                        <p>Fill out the form below and we'll be in touch within 24 hours</p>
                        <hr>
                    </div>

                    <?php
                    // Display success/error messages
                    if (isset($_SESSION['demo_success'])) {
                        echo '<div class="alert alert-success">' . $_SESSION['demo_success'] . '</div>';
                        unset($_SESSION['demo_success']);
                    }
                    if (isset($_SESSION['demo_error'])) {
                        echo '<div class="alert alert-error">' . $_SESSION['demo_error'] . '</div>';
                        unset($_SESSION['demo_error']);
                    }
                    ?>

                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Full Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="company">Company Name <span class="required">*</span></label>
                                <input type="text" id="company" name="company" required value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="company_size">Company Size</label>
                            <select id="company_size" name="company_size">
                                <option value="">Select company size</option>
                                <option value="1-10" <?php echo ($_POST['company_size'] ?? '') === '1-10' ? 'selected' : ''; ?>>1-10 employees</option>
                                <option value="11-50" <?php echo ($_POST['company_size'] ?? '') === '11-50' ? 'selected' : ''; ?>>11-50 employees</option>
                                <option value="51-200" <?php echo ($_POST['company_size'] ?? '') === '51-200' ? 'selected' : ''; ?>>51-200 employees</option>
                                <option value="201-500" <?php echo ($_POST['company_size'] ?? '') === '201-500' ? 'selected' : ''; ?>>201-500 employees</option>
                                <option value="500+" <?php echo ($_POST['company_size'] ?? '') === '500+' ? 'selected' : ''; ?>>500+ employees</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="preferred_date">Preferred Date</label>
                                <input type="date" id="preferred_date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['preferred_date'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="preferred_time">Preferred Time</label>
                                <select id="preferred_time" name="preferred_time">
                                    <option value="">Select preferred time</option>
                                    <option value="09:00" <?php echo ($_POST['preferred_time'] ?? '') === '09:00' ? 'selected' : ''; ?>>9:00 AM</option>
                                    <option value="10:00" <?php echo ($_POST['preferred_time'] ?? '') === '10:00' ? 'selected' : ''; ?>>10:00 AM</option>
                                    <option value="11:00" <?php echo ($_POST['preferred_time'] ?? '') === '11:00' ? 'selected' : ''; ?>>11:00 AM</option>
                                    <option value="14:00" <?php echo ($_POST['preferred_time'] ?? '') === '14:00' ? 'selected' : ''; ?>>2:00 PM</option>
                                    <option value="15:00" <?php echo ($_POST['preferred_time'] ?? '') === '15:00' ? 'selected' : ''; ?>>3:00 PM</option>
                                    <option value="16:00" <?php echo ($_POST['preferred_time'] ?? '') === '16:00' ? 'selected' : ''; ?>>4:00 PM</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">Tell us about your needs</label>
                            <textarea id="message" name="message" placeholder="What specific features are you most interested in? What challenges are you facing with your current system?"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Schedule My Demo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Set minimum date to today
        document.getElementById('preferred_date').min = new Date().toISOString().split('T')[0];
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['name', 'email', 'company'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.style.borderColor = 'var(--danger)';
                    isValid = false;
                } else {
                    input.style.borderColor = 'var(--gray-200)';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    </script>
    <?php renderThemeScript(); ?>

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>