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

    // Query to fetch join requests for the group where status is 'pending'
    $joinRequestQuery = "
    SELECT
        gm.membership_id,  -- Corrected column name for membership_id
        u.name AS user_name,
        gm.join_request_date
    FROM
        group_membership gm
    JOIN
        users u ON gm.user_id = u.id
    WHERE
        gm.group_id = ? AND gm.status = 'pending'
    ORDER BY
        gm.join_request_date ASC
    ";

    $stmt = $conn->prepare($joinRequestQuery);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if any join requests are found
    if ($result->num_rows > 0) {
        $joinRequests = [];

        // Fetch all join requests
        while ($row = $result->fetch_assoc()) {
            $joinRequests[] = [
                "membership_id" => $row['membership_id'],  // Use membership_id
                "user_name" => $row['user_name'],
                "join_request_date" => $row['join_request_date']
            ];
        }

        // Return the join request details as a response
        echo json_encode([
            "status" => "success",
            "message" => "Join requests fetched successfully.",
            "data" => $joinRequests
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No join requests found for this group."
        ]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
