<?php
/**
 * Language Switcher Controller
 * This file handles language switching requests
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include language helper functions
require_once __DIR__ . '/includes/language_helpers.php';

// Check if language parameter exists
if (isset($_GET['lang'])) {
    $language = $_GET['lang'];
    
    // Set the language
    set_language($language);
    
    // Redirect back to the referring page or home if not available
    $redirect_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
    
    // Remove any existing lang parameter from URL to avoid duplication
    $redirect_to = preg_replace('/(\?|&)lang=[^&]*(&|$)/', '$1', $redirect_to);
    
    // Fix URL if it ends with ? or &
    if (substr($redirect_to, -1) === '?' || substr($redirect_to, -1) === '&') {
        $redirect_to = substr($redirect_to, 0, -1);
    }
    
    // Redirect
    header('Location: ' . $redirect_to);
    exit;
}

// If no language parameter, redirect to home
header('Location: index.php');
exit; 