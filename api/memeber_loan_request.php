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
    // Get group_id, amount, reason, and return_time from frontend
    $input = json_decode(file_get_contents("php://input"), true);
    $group_id = intval($input['group_id']);
    $amount = floatval($input['amount']);
    $reason = $input['reason'];
    $return_time = $input['return_time'];

    if (!$group_id || !$amount || !$reason || !$return_time) {
        echo json_encode(["status" => "error", "message" => "Missing required fields."]);
        exit();
    }

    // Validate return_time: It must be greater than or equal to the current date
    $current_date = date('Y-m-d'); // Get the current date in 'YYYY-MM-DD' format
    if ($return_time < $current_date) {
        echo json_encode(["status" => "error", "message" => "Return date must be today or a future date."]);
        exit();
    }

    // First, check if the group exists and if the emergency fund is sufficient
    $group_check_query = "SELECT emergency_fund FROM my_group WHERE group_id = ?";
    $stmt = $conn->prepare($group_check_query);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $group_result = $stmt->get_result();

    if ($group_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Group does not exist."]);
        exit();
    }

    $group_data = $group_result->fetch_assoc();
    $emergency_fund = $group_data['emergency_fund'];

    // Check if the emergency fund is sufficient
    if ($emergency_fund < $amount) {
        echo json_encode(["status" => "error", "message" => "Insufficient emergency fund."]);
        exit();
    }

    // Check if the user is a member of the group
    $membership_check_query = "SELECT status FROM group_membership WHERE user_id = ? AND group_id = ? and status = 'aproved'";
    $stmt = $conn->prepare($membership_check_query);
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    $membership_result = $stmt->get_result();

    if ($membership_result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "You are not a member of this group."]);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert loan request into the loan_request table
        $insert_query = "
            INSERT INTO loan_request (user_id, group_id, reason, amount, status, return_time) 
            VALUES (?, ?, ?, ?, 'pending', ?)
        ";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("iisds", $user_id, $group_id, $reason, $amount, $return_time);

        if (!$stmt->execute()) {
            throw new Exception("Failed to create loan request.");
        }

        // Create a poll asking the group members to approve or deny the loan
        $poll_question = "This member wants to take $amount as a loan. Do you agree?";
        $poll_query = "
            INSERT INTO polls (group_id, poll_question, option_1, option_2, status) 
            VALUES (?, ?, 'Yes', 'No', 'active')
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
            "message" => "Loan request submitted and poll created successfully." 
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
