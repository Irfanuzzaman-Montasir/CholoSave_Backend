<?php
include 'db.php';
include 'session.php'; 

header("Content-Type: application/json");

// Check if the user is logged in
if (!isLoggedIn()) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

// Get user_id from session
$user_id = getUserId();

// Query to get the list of groups for the logged-in user
$query = "
    SELECT 
        g.group_id, 
        g.group_name, 
        g.dps_type, 
        g.amount AS installment, 
        COUNT(gm2.user_id) AS members_count,
        CASE WHEN g.group_admin_id = ? THEN 1 ELSE 0 END AS is_admin
    FROM 
        group_membership gm
    INNER JOIN 
        my_group g 
    ON 
        gm.group_id = g.group_id
    LEFT JOIN 
        group_membership gm2 
    ON 
        g.group_id = gm2.group_id AND gm2.status = 'approved'
    WHERE 
        gm.user_id = ? AND gm.status = 'approved'
    GROUP BY 
        g.group_id
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result) {
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = [
            "group_id" => $row['group_id'],
            "group_name" => $row['group_name'],
            "dps_type" => $row['dps_type'],
            "installment" => $row['installment'],
            "members_count" => $row['members_count'],
            "is_admin" => (bool)$row['is_admin']
        ];
    }

    if (empty($groups)) {
        // If no groups found
        echo json_encode(["status" => "success", "message" => "You have not joined any groups yet."]);
    } else {
        // Send success response with groups
        echo json_encode(["status" => "success", "groups" => $groups]);
    }
} else {
    // Send error response
    echo json_encode(["status" => "error", "message" => "Failed to fetch groups."]);
}

$stmt->close();
$conn->close();
?>
