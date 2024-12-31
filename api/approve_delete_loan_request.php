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
    // Get group_id, loan_id, and status from the POST data
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['group_id'], $data['loan_id'], $data['status'])) {
        echo json_encode(["status" => "error", "message" => "Missing parameters."]);
        exit();
    }

    $group_id = intval($data['group_id']);
    $loan_id = intval($data['loan_id']);
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

    // If approved, we need to deduct the emergency fund
    if ($status === 'approved') {
        // Get the loan amount
        $loanAmountQuery = "SELECT amount FROM loan_request WHERE id = ? AND group_id = ?";
        $stmt = $conn->prepare($loanAmountQuery);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ii", $loan_id, $group_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Loan request not found."]);
            exit();
        }

        $loan = $result->fetch_assoc();
        $loan_amount = $loan['amount'];
        $stmt->close();

        // Check if the group has enough emergency fund
        $fundQuery = "SELECT emergency_fund FROM my_group WHERE group_id = ?";
        $stmt = $conn->prepare($fundQuery);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(["status" => "error", "message" => "Group not found."]);
            exit();
        }

        $group = $result->fetch_assoc();
        $emergency_fund = $group['emergency_fund'];

        // Check if there's enough emergency fund
        if ($emergency_fund < $loan_amount) {
            echo json_encode(["status" => "error", "message" => "Insufficient emergency fund."]);
            exit();
        }

        // Deduct the loan amount from the emergency fund
        $new_emergency_fund = $emergency_fund - $loan_amount;
        $updateFundQuery = "UPDATE my_group SET emergency_fund = ? WHERE group_id = ?";
        $stmt = $conn->prepare($updateFundQuery);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("di", $new_emergency_fund, $group_id);
        $stmt->execute();
        $stmt->close();
    }

    // Update the loan request status and approve_date if approved
    $updateQuery = "UPDATE loan_request SET status = ?, approve_date = IF(? = 'approved', CURDATE(), NULL) WHERE id = ? AND group_id = ?";
    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ssii", $status, $status, $loan_id, $group_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "status" => "success",
            "message" => "Loan request " . $status . " successfully."
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to update loan request."
        ]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
