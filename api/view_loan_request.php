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

// Check if request method is GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get group_id from URL parameters
    if (!isset($_GET['group_id'])) {
        echo json_encode(["status" => "error", "message" => "Missing group_id."]);
        exit();
    }

    $group_id = intval($_GET['group_id']);

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

    // Query to fetch loan requests details
    $loanQuery = "
    SELECT
        lr.id, 
        u.name AS user_name, 
        lr.amount, 
        lr.reason, 
        lr.return_time,
        IFNULL(SUM(gc.amount), 0) AS group_contribution
    FROM
        loan_request lr
    JOIN
        users u ON lr.user_id = u.id
    LEFT JOIN
        savings gc ON lr.user_id = gc.user_id AND lr.group_id = gc.group_id
    WHERE
        lr.group_id = ? AND lr.status = 'pending'
    GROUP BY
        lr.id, u.name, lr.amount, lr.reason, lr.return_time
    ORDER BY
        lr.return_time ASC
    ";

    $stmt = $conn->prepare($loanQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if any loan requests are found
    if ($result->num_rows > 0) {
        $loanRequests = [];

        // Fetch all loan requests
        while ($row = $result->fetch_assoc()) {
            $loanRequests[] = [
                "id" => $row['id'], // Include the loan id here
                "name" => $row['user_name'],
                "amount" => $row['amount'],
                "reason" => $row['reason'],
                "return_date" => $row['return_time'],
                "group_contribution" => $row['group_contribution']
            ];
        }

        // Return the loan request details as a response
        echo json_encode([
            "status" => "success",
            "message" => "Loan requests fetched successfully.",
            "data" => $loanRequests
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No loan requests found for this group."
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
