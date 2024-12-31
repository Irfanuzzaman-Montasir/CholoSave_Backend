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

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get group_id, amount, and investment_type from frontend
    $input = json_decode(file_get_contents("php://input"), true);
    $group_id = intval($input['group_id']);
    $amount = floatval($input['amount']);
    $investment_type = $input['investment_type'];

    if (!$group_id || !$amount || !$investment_type) {
        echo json_encode(["status" => "error", "message" => "Missing required fields."]);
        exit();
    }

    // Verify if the user is the admin of the group
    $admin_check_query = "SELECT group_id FROM my_group WHERE group_admin_id = ? AND group_id = ?";
    $stmt = $conn->prepare($admin_check_query);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        exit();
    }
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "You are not the admin of this group."]);
        exit();
    }
    $stmt->close();

    // Insert investment data into the investments table
    $insert_query = "
        INSERT INTO investments (group_id, amount, investment_type) 
        VALUES (?, ?, ?)
    ";
    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        exit();
    }
    $stmt->bind_param("ids", $group_id, $amount, $investment_type);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Investment data added successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to add investment data."]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
