<?php
    $conn = new mysqli("localhost", "root", "", "travelagency_saas");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // Set UTF-8 character set
    if (!$conn->set_charset("utf8mb4")) {
        printf("Error loading character set utf8mb4: %s\n", $conn->error);
        exit();
    }
?>