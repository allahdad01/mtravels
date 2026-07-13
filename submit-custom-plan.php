<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/helpers.php';

header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: custom-plan-request.php');
    exit();
}

$contact_name = trim($_POST['contact_name'] ?? '');
$contact_email = trim($_POST['contact_email'] ?? '');
$contact_phone = trim($_POST['contact_phone'] ?? '');
$agency_name = trim($_POST['agency_name'] ?? '');
$selected_features_json = $_POST['selected_features'] ?? '';
$max_users = intval($_POST['max_users'] ?? 1);
$notes = trim($_POST['notes'] ?? '');
$errors = [];

if (empty($contact_name)) {
    $errors[] = 'Full name is required.';
}
if (empty($contact_email) || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (empty($contact_phone)) {
    $errors[] = 'Phone number is required.';
}

$selected_features = json_decode($selected_features_json, true);
if (empty($selected_features) || !is_array($selected_features)) {
    $errors[] = 'Please select at least one feature.';
}

if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO custom_plan_requests (contact_name, contact_email, contact_phone, agency_name, selected_features, max_users, notes, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())");
        $stmt->execute([$contact_name, $contact_email, $contact_phone, $agency_name, $selected_features_json, $max_users, $notes]);

        $request_id = $pdo->lastInsertId();

        $to = getenv('ADMIN_EMAIL') ?: 'allahdadmuhammadi01@gmail.com';
        $subject = 'New Custom Plan Request #' . $request_id . ' from ' . $contact_name;
        $feature_count = count($selected_features);
        $message = "New Custom Plan Request\n\n";
        $message .= "Name: $contact_name\nEmail: $contact_email\nPhone: $contact_phone\n";
        if ($agency_name) $message .= "Agency: $agency_name\n";
        $message .= "Users: $max_users\nFeatures Selected: $feature_count\n";
        if ($notes) $message .= "Notes: $notes\n\n";
        $message .= "View in admin: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . "/super_admin/manage_custom_plan_requests.php";

        @mail($to, $subject, $message, "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'mtravels.com'));

        header('Location: custom-plan-request.php?success=submitted');
        exit();
    } catch (PDOException $e) {
        error_log("Custom Plan Request Error: " . $e->getMessage());
        $errors[] = 'An error occurred while submitting your request. Please try again later.';
    }
}

if (!empty($errors)) {
    $_SESSION['cp_errors'] = $errors;
    $_SESSION['cp_form_data'] = $_POST;
    header('Location: custom-plan-request.php?error=validation');
    exit();
}
