<?php
// Start session and include session.php
include 'session.php';

// Include database connection
include 'db.php';

// Set content type to JSON
header("Content-Type: application/json");

// Check if the user is logged in
if (!isLoggedIn()) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit();
}


//  query to get group details and member count
$query = "
    SELECT 
        g.group_id, 
        g.group_name, 
        g.dps_type, 
        g.amount AS installment, 
        COUNT(gm.user_id) AS members_count 
    FROM 
        my_group g
    LEFT JOIN 
        group_membership gm 
    ON 
        g.group_id = gm.group_id AND gm.status = 'approved'
    GROUP BY 
        g.group_id
";

$result = $conn->query($query);

if ($result) {
    $groups = [];
    while ($row = $result->fetch_assoc()) {
        $groups[] = [
            "group_id" => $row['group_id'],
            "group_name" => $row['group_name'],
            "dps_type" => $row['dps_type'],
            "installment" => $row['installment'],
            "members_count" => $row['members_count']
        ];
    }

    //  success response
    echo json_encode(["status" => "success", "groups" => $groups]);
} else {
    //  error response
    echo json_encode(["status" => "error", "message" => "Failed to fetch groups."]);
}
?>
