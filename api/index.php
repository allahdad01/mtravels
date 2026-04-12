<?php
// API Index - Prevent direct access
// This directory contains API endpoints, not meant for direct browsing


// Deny access
http_response_code(403);
die(json_encode([
    'error' => 'Forbidden',
    'message' => 'Direct access to API directory is not allowed'
]));
?>
