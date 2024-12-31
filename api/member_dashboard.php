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

// Verify if the user is part of the group
$verify_query = "SELECT 1 FROM group_membership WHERE user_id = ? AND group_id = ? AND status = 'approved'";
$verify_stmt = $conn->prepare($verify_query);
$verify_stmt->bind_param("ii", $user_id, $group_id);
$verify_stmt->execute();
$verify_result = $verify_stmt->get_result();

if ($verify_result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Access denied. You are not a member of this group."]);
    $verify_stmt->close();
    $conn->close();
    exit;
}
$verify_stmt->close();

// Total savings of all members query
$total_group_savings_query = "SELECT IFNULL(SUM(amount), 0) AS total_group_savings FROM savings WHERE group_id = ?";
$total_group_stmt = $conn->prepare($total_group_savings_query);
$total_group_stmt->bind_param("i", $group_id);
$total_group_stmt->execute();
$total_group_savings = $total_group_stmt->get_result()->fetch_assoc()["total_group_savings"];
$total_group_stmt->close();

// This month's savings query
$month_savings_query = "SELECT IFNULL(SUM(amount), 0) AS this_month_savings FROM savings WHERE group_id = ? AND MONTH(created_at) = MONTH(CURRENT_DATE)";
$month_stmt = $conn->prepare($month_savings_query);
$month_stmt->bind_param("i", $group_id);
$month_stmt->execute();
$this_month_savings = $month_stmt->get_result()->fetch_assoc()["this_month_savings"];
$month_stmt->close();

// Total members query
$total_members_query = "SELECT COUNT(*) AS total_members FROM group_membership WHERE group_id = ? AND status = 'approved'";
$members_stmt = $conn->prepare($total_members_query);
$members_stmt->bind_param("i", $group_id);
$members_stmt->execute();
$total_members = $members_stmt->get_result()->fetch_assoc()["total_members"];
$members_stmt->close();

// New members this month query
$new_members_query = "SELECT COUNT(*) AS new_members FROM group_membership WHERE group_id = ? AND status = 'approved' AND MONTH(join_date) = MONTH(CURRENT_DATE)";
$new_stmt = $conn->prepare($new_members_query);
$new_stmt->bind_param("i", $group_id);
$new_stmt->execute();
$new_members = $new_stmt->get_result()->fetch_assoc()["new_members"];
$new_stmt->close();

// Total polls query
$total_polls_query = "SELECT COUNT(*) AS total_polls FROM polls WHERE group_id = ? AND status = 'active'";
$polls_stmt = $conn->prepare($total_polls_query);
$polls_stmt->bind_param("i", $group_id);
$polls_stmt->execute();
$total_polls = $polls_stmt->get_result()->fetch_assoc()["total_polls"];
$polls_stmt->close();

// Emergency fund query
$emergency_query = "SELECT emergency_fund FROM my_group WHERE group_id = ?";
$emergency_stmt = $conn->prepare($emergency_query);
$emergency_stmt->bind_param("i", $group_id);
$emergency_stmt->execute();
$emergency_fund = $emergency_stmt->get_result()->fetch_assoc()["emergency_fund"];
$emergency_stmt->close();

// Goal amount query
$goal_query = "SELECT goal_amount FROM my_group WHERE group_id = ?";
$goal_stmt = $conn->prepare($goal_query);
$goal_stmt->bind_param("i", $group_id);
$goal_stmt->execute();
$goal_amount = $goal_stmt->get_result()->fetch_assoc()["goal_amount"];
$goal_stmt->close();

// Fetch active poll details
$poll_query = "SELECT poll_id, poll_question, option_1, option_2 FROM polls WHERE group_id = ? AND status = 'active'";
$poll_stmt = $conn->prepare($poll_query);
$poll_stmt->bind_param("i", $group_id);
$poll_stmt->execute();
$poll_result = $poll_stmt->get_result();
$polls = [];
while ($poll = $poll_result->fetch_assoc()) {
    $polls[] = $poll;
}
$poll_stmt->close();

// Return dashboard data
echo json_encode([
    "status" => "success",
    "dashboard" => [
        "total_group_savings" => $total_group_savings,
        "this_month_savings" => $this_month_savings,
        "total_members" => $total_members,
        "new_members" => $new_members,
        "total_polls" => $total_polls,
        "emergency_fund" => $emergency_fund,
        "goal_amount" => $goal_amount
    ],
    "polls" => $polls
]);

$conn->close();
?>
