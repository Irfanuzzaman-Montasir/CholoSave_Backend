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
    // Get group_id from frontend
    $input = json_decode(file_get_contents("php://input"), true);
    $group_id = intval($input['group_id']);

    if (!$group_id) {
        echo json_encode(["status" => "error", "message" => "Invalid group ID."]);
        exit();
    }

    // First check if the group exists
    $group_check_query = "SELECT group_id, group_name FROM my_group WHERE group_id = ?";
    $stmt = $conn->prepare($group_check_query);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $group_result = $stmt->get_result();

    if ($group_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Group does not exist."]);
        exit();
    }

    $group_data = $group_result->fetch_assoc();
    $group_name = $group_data['group_name'];

    // Check if user is already a member of the group or if there is a pending request
    $check_membership_query = "SELECT status FROM group_membership WHERE user_id = ? AND group_id = ?";
    $stmt = $conn->prepare($check_membership_query);
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Fetch the status of the user's membership request
        $row = $result->fetch_assoc();
        $status = $row['status'];

        if ($status === 'pending') {
            echo json_encode(["status" => "error", "message" => "Your join request is in progress. Please wait."]);
        } elseif ($status === 'approved') {
            echo json_encode(["status" => "error", "message" => "You are already a member of this group."]);
        }
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert request to join group with status 'pending' and the current date as join_request_date
        $insert_query = "INSERT INTO group_membership (user_id, group_id, status, join_request_date) 
                         VALUES (?, ?, 'pending', CURDATE())";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ii", $user_id, $group_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create group membership request.");
        }

        // Create a poll asking the group members to approve or deny the join request
        $poll_question = "A user wants to join the group. Do you approve?";
        $poll_query = "
            INSERT INTO polls (group_id, poll_question, status)
            VALUES (?, ?,'active')
        ";
        $stmt = $conn->prepare($poll_query);
        $stmt->bind_param("is", $group_id, $poll_question);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create approval poll.");
        }

        // If everything is successful, commit the transaction
        $conn->commit();
        echo json_encode([ 
            "status" => "success", 
            "message" => "Join request sent and poll created successfully."
        ]);

    } catch (Exception $e) {
        // If there's an error, rollback the transaction
        $conn->rollback();
        echo json_encode([ 
            "status" => "error", 
            "message" => "An error occurred: " . $e->getMessage() 
        ]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
