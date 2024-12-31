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

// Get group_id from frontend (POST request)
$input = json_decode(file_get_contents("php://input"), true);
$group_id = intval($input['group_id'] ?? 0);

if (!$group_id) {
    echo json_encode(["status" => "error", "message" => "Invalid group ID."]);
    exit;
}

// Query to check if the user is part of the group and fetch group details
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
        gm.user_id = ? AND gm.group_id = ? AND gm.status = 'approved'
    GROUP BY 
        g.group_id
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iii", $user_id, $user_id, $group_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    // Check if the user is the admin
    $is_admin = (bool)$row['is_admin'];

    // Return success response with user role
    echo json_encode([
        "status" => "success",
        "message" => "Group found.",
        "group" => [
            "group_id" => $row['group_id'],
            "group_name" => $row['group_name'],
            "dps_type" => $row['dps_type'],
            "installment" => $row['installment'],
            "members_count" => $row['members_count'],
            "is_admin" => $is_admin
        ]
    ]);
} else {
    // Send error response if the user is not part of the group or if access is denied
    echo json_encode(["status" => "error", "message" => "Group not found or access denied."]);
}

$stmt->close();
$conn->close();
?>
