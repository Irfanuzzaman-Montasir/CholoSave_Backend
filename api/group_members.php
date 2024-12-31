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

    // Query to fetch group members details
    $query = "
        SELECT
            u.id, u.name, gm.join_date,
            IFNULL(SUM(s.amount), 0) AS total_savings
        FROM
            users u
        LEFT JOIN
            group_membership gm ON u.id = gm.user_id
        LEFT JOIN
            savings s ON u.id = s.user_id AND s.group_id = gm.group_id
        WHERE
            gm.group_id = ? and status ='approved'
        GROUP BY
            u.id, u.name, gm.join_date
    ";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if any members are found
    if ($result->num_rows > 0) {
        $members = [];

        // Fetch all group members
        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'join_date' => $row['join_date'],
                'total_savings' => $row['total_savings']
            ];
        }

        // Return the member details as a response
        echo json_encode([
            "status" => "success",
            "message" => "Group members fetched successfully.",
            "data" => $members
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No members found for this group."
        ]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>
