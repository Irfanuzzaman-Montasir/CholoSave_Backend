<?php
include_once 'session.php';
include 'db.php';
header("Content-Type: application/json");

// Check if the user is logged in
if (!isLoggedIn()) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit();
}

// Get user_id from session
$user_id = getUserId();

// Check if the user is an admin
$adminQuery = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($adminQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0 || $result->fetch_assoc()['role'] !== 'admin') {
    echo json_encode(["status" => "error", "message" => "Access denied. Admin only."]);
    exit();
}
$stmt->close();

// Fetch contact_us data
$contactQuery = "SELECT name, email, description, created_at FROM contact_us ORDER BY created_at DESC";
$result = $conn->query($contactQuery);

if ($result->num_rows > 0) {
    $contacts = [];
    while ($row = $result->fetch_assoc()) {
        $contacts[] = [
            "name" => $row['name'],
            "email" => $row['email'],
            "description" => $row['description'],
            "created_at" => $row['created_at']
        ];
    }
    echo json_encode(["status" => "success", "data" => $contacts]);
} else {
    echo json_encode(["status" => "error", "message" => "No contact requests found."]);
}

$conn->close();
?>
    