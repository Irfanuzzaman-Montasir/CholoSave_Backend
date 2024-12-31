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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get input from frontend
    $input = json_decode(file_get_contents("php://input"), true);

    // Extract and sanitize the input data
    $group_name = htmlspecialchars(trim($input['group_name']));
    $group_admin_id = getUserId(); // Use the logged-in user's ID as group admin
    $dps_type = htmlspecialchars(trim($input['dps_type']));
    $time_period = intval($input['time_period']);
    $amount = floatval($input['amount']);
    $start_date = htmlspecialchars(trim($input['start_date']));
    $goal_amount = intval($input['goal_amount']);
    $warning_time = intval($input['warning_time']);
    $emergency_fund = floatval($input['emergency_fund']);
    $bKash = htmlspecialchars(trim($input['bKash']));
    $Rocket = htmlspecialchars(trim($input['Rocket'] ?? ''));
    $Nagad = htmlspecialchars(trim($input['Nagad'] ?? ''));
    $description = htmlspecialchars(trim($input['description']));

    // Validate required fields
    if (
        empty($group_name) || empty($group_admin_id) || empty($dps_type) ||
        empty($time_period) || empty($amount) || empty($start_date) ||
        empty($goal_amount) || empty($warning_time) || empty($emergency_fund) ||
        empty($description) || empty($bKash)
    ) {
        echo json_encode(["status" => "error", "message" => "All fields except Rocket and Nagad are required."]);
        exit();
    }

    // Validate DPS type (in frontend there will be dropdown)
    if (!in_array($dps_type, ['weekly', 'monthly'])) {
        echo json_encode(["status" => "error", "message" => "Invalid DPS type. Must be 'weekly' or 'monthly'."]);
        exit();
    }

    // Insert into database
    $insert_query = "
        INSERT INTO my_group (
            group_name, group_admin_id, dps_type, time_period, amount, 
            start_date, goal_amount, warning_time, emergency_fund, 
            bKash, Rocket, Nagad, description
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($insert_query);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Failed to prepare query.", "error" => $conn->error]);
        exit();
    }

    $stmt->bind_param(
        "sisidiiidssss",
        $group_name, $group_admin_id, $dps_type, $time_period, $amount,
        $start_date, $goal_amount, $warning_time, $emergency_fund,
        $bKash, $Rocket, $Nagad, $description
    );

    if ($stmt->execute()) {
        // Get the generated group_id
        $group_id = $stmt->insert_id;

        // Store group_id in session for the next page
        $_SESSION['group_id'] = $group_id;

        // Insert admin into group_membership table
        $current_date = date('Y-m-d'); // Current date
        $membership_query = "
            INSERT INTO group_membership (group_id, user_id, status, is_admin, join_date) 
            VALUES (?, ?, 'approved', 1, ?)
        ";

        $membership_stmt = $conn->prepare($membership_query);
        if ($membership_stmt) {
            $membership_stmt->bind_param("iis", $group_id, $group_admin_id, $current_date);
            if ($membership_stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "Group created successfully!", "group_id" => $group_id]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to add admin to group_membership.", "error" => $membership_stmt->error]);
            }
            $membership_stmt->close();
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to prepare group_membership query.", "error" => $conn->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create group.", "error" => $stmt->error]);
    }

    $stmt->close();
} else {
    // Invalid request method
    echo json_encode(["status" => "error", "message" => "Invalid request method. Use POST."]);
}

// Close the database connection
$conn->close();
?>
