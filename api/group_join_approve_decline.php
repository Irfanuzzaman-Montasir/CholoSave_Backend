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
    // Get group_id and membership_id from the POST data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['group_id'], $data['membership_id'], $data['status'])) {
        echo json_encode(["status" => "error", "message" => "Missing parameters."]);
        exit();
    }

    $group_id = intval($data['group_id']);
    $membership_id = intval($data['membership_id']);
    $status = $data['status']; // 'approved' or 'declined'

    // Verify if the user is the admin of the given group
    $groupAdminQuery = "SELECT group_id FROM my_group WHERE group_admin_id = ? AND group_id = ?";
    $stmt = $conn->prepare($groupAdminQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "You are not the admin of this group."]);
        exit();
    }
    $stmt->close();

    // Validate the status
    if ($status !== 'approved' && $status !== 'declined') {
        echo json_encode(["status" => "error", "message" => "Invalid status. Please use 'approved' or 'declined'."]);
        exit();
    }

    // Update the join request status and set the join_date if approved
    $updateQuery = "
    UPDATE group_membership 
    SET status = ?, join_date = IF(? = 'approved', CURDATE(), join_date)
    WHERE membership_id = ? AND group_id = ?";
    
    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("siii", $status, $status, $membership_id, $group_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Join request " . $status . " successfully."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update join request."
        ]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
