<?php
// Contact form handler for MTravels landing page
session_start();

// Include database connection
require_once 'includes/db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $subject = trim(filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING));
    $message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING));

    // Validate required fields
    $errors = [];

    if (empty($name)) {
        $errors[] = 'Name is required';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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

            // Email headers
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . htmlspecialchars($name) . " <" . $email . ">" . "\r\n";
            $headers .= "Reply-To: " . $email . "\r\n";

            // Try to send email
            $email_sent = false;
            if (function_exists('mail')) {
                $email_sent = mail($to, $email_subject, $email_body, $headers);
            }

            // Always try to store in database (even if email fails)
            $db_stored = false;
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$name, $email, $subject, $message]);
                $db_stored = true;
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