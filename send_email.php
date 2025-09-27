<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $subject = trim($_POST["subject"]);
    $message = trim($_POST["message"]);
    $recipient = 'almuqadas_travels@yahoo.com'; // Hard-coded for security
    
    // Validate inputs
    if (empty($name) || empty($email) || empty($message)) {
        echo "Please fill all required fields";
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format";
        exit;
    }
    
    // Set default subject if empty
    if (empty($subject)) {
        $subject = "New Contact Form Submission";
    }
    
    // Format email
    $email_content = "Name: $name\n";
    $email_content .= "Email: $email\n\n";
    $email_content .= "Message:\n$message\n";
    
    // Set email headers
    $headers = "From: $name <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    
    // Send email
    if (mail($recipient, $subject, $email_content, $headers)) {
        echo "success";
    } else {
        echo "Failed to send the message. Please try again later.";
    }
} else {
    echo "Invalid request method";
}
?>