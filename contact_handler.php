<?php
// Contact form handler for MTravels landing page
session_start();

// Include database connection and security utilities
require_once 'includes/db.php';
require_once 'includes/InputValidator.php';
require_once 'includes/RateLimiter.php';

// Generate CSRF token if it doesn't exist
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection - CRITICAL SECURITY
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF attack detected on contact form from IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['contact_error'] = 'Security validation failed. Please try again.';
        header('Location: index.php#contact');
        exit;
    }
    
    // Rate limit check - 3 requests per hour per IP (contact is more sensitive)
    if (!RateLimiter::isAllowed($_SERVER['REMOTE_ADDR'], 'contact_requests_per_hour', 0, 'ip')) {
        $_SESSION['contact_error'] = 'You have submitted too many contact requests. Please try again later.';
        header('Location: index.php#contact');
        exit;
    }
    
    // Regenerate CSRF token after validation
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Validate and sanitize input using InputValidator
    $name = InputValidator::getString($_POST['name'] ?? '', 100);
    $email = InputValidator::getEmail($_POST['email'] ?? '');
    $subject = InputValidator::getString($_POST['subject'] ?? '', 100);
    $message = InputValidator::getString($_POST['message'] ?? '', 1000);

    // Validate required fields
    $errors = [];

    if (empty($name)) {
        $errors[] = 'Name is required';
    }

    if (empty($email)) {
        $errors[] = 'Valid email is required';
    }

    if (empty($subject)) {
        $errors[] = 'Subject is required';
    }

    if (empty($message)) {
        $errors[] = 'Message is required';
    }

    // If no errors, process the contact form
    if (empty($errors)) {
        try {
            // Get platform settings for notification email
            $platform_settings = [];
            $stmt = $pdo->prepare("SELECT `key`, `value` FROM platform_settings ORDER BY id");
            $stmt->execute();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $platform_settings[$row['key']] = $row['value'];
            }

            // Prepare email content
            $to = $platform_settings['contact_email'] ?? 'allahdadmuhammadi01@gmail.com';
            $email_subject = "MTravels Contact Form: " . $subject;
            $email_body = "
            <html>
            <head>
                <title>MTravels Contact Form Submission</title>
            </head>
            <body>
                <h2>New Contact Form Submission</h2>
                <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p><strong>Message:</strong></p>
                <p>" . nl2br(htmlspecialchars($message)) . "</p>
                <hr>
                <p><small>This message was sent from the MTravels landing page contact form.</small></p>
            </body>
            </html>
            ";

            // Email headers - Sanitize email to prevent header injection
            $email_safe = preg_replace('/[^a-zA-Z0-9@._-]/', '', $email);
            $name_safe = htmlspecialchars($name);
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . $name_safe . " <" . $email_safe . ">" . "\r\n";
            $headers .= "Reply-To: " . $email_safe . "\r\n";

            // Try to send email
            $email_sent = false;
            if (function_exists('mail')) {
                $email_sent = mail($to, $email_subject, $email_body, $headers);
            }

            // Always try to store in database (even if email fails)
            $db_stored = false;
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $subject, $message, $_SERVER['REMOTE_ADDR']]);
                $db_stored = true;
                
                // Record action in rate limiter
                RateLimiter::recordAction($_SERVER['REMOTE_ADDR'], 'contact_requests_per_hour', 1, $_SERVER['REMOTE_ADDR'], 'ip');
            } catch (Exception $db_error) {
                error_log("Database storage error: " . $db_error->getMessage());
                // Continue even if database storage fails
            }

            // Success response - if either email was sent OR message was stored in DB
            if ($email_sent || $db_stored) {
                $success_message = 'Thank you for your message! ';
                if ($email_sent && $db_stored) {
                    $success_message .= 'We have received your message and will get back to you soon.';
                } elseif ($email_sent) {
                    $success_message .= 'Your message has been sent successfully.';
                } elseif ($db_stored) {
                    $success_message .= 'Your message has been saved and we will get back to you soon.';
                }
                $_SESSION['contact_success'] = $success_message;
            } else {
                $_SESSION['contact_error'] = 'Sorry, there was an error processing your message. Please try again or contact us directly.';
            }

        } catch (Exception $e) {
            error_log("Contact form error: " . $e->getMessage());
            $_SESSION['contact_error'] = 'Sorry, there was an error processing your message. Please try again.';
        }
    } else {
        $_SESSION['contact_error'] = implode('<br>', $errors);
    }

    // Redirect back to contact section
    header('Location: index.php#contact');
    exit;
} else {
    // If not POST request, redirect to home
    header('Location: index.php');
    exit;
}
?>