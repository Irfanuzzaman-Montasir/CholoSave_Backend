<?php
include 'session.php'; // Include the session management file

header("Content-Type: application/json");

// Check if the user is logged in
if (!isLoggedIn()) {
    echo json_encode(["status" => "error", "message" => "User is not logged in."]);
    exit();
}

// Destroy the session
session_start(); // Start the session
session_unset(); // Remove all session variables
session_destroy(); // Destroy the session

// Send response indicating successful logout
echo json_encode(["status" => "success", "message" => "Logged out successfully."]);
?>
